<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Support\ListingColumns;
use RadThemes\RadpackCrm\Support\Money;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\CP\PublishForm;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Statamic;

class TransactionsController extends CpController
{
    public function index(): Response
    {
        $this->authorize('view crm');

        $thisMonth = Transaction::query()->where('currency', Settings::currency())->whereDate('date', '>=', now()->startOfMonth());
        $lastMonth = Transaction::query()->where('currency', Settings::currency())->whereBetween('date', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]);

        return Inertia::render('radpack-crm::Transactions/Index', [
            'columns' => ListingColumns::for('transactions'),
            'jsonUrl' => cp_route('radpack-crm.transactions.json'),
            'createUrl' => cp_route('radpack-crm.transactions.create'),
            'statuses' => collect(Transaction::STATUSES)->map(fn ($status) => ['value' => $status, 'label' => __(ucfirst($status))]),
            'summary' => [
                ['label' => __('Revenue this month'), 'value' => Money::format(Transaction::revenue($thisMonth), Settings::currency())],
                ['label' => __('Revenue last month'), 'value' => Money::format(Transaction::revenue($lastMonth), Settings::currency())],
            ],
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $this->authorize('view crm');

        $query = Transaction::query()->with(['contact', 'invoice']);

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($contact) => $contact->search($search)));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = in_array($request->input('sort'), ['date', 'amount', 'status', 'title'], true) ? $request->input('sort') : 'date';
        $query->orderBy($sort, $request->input('order') === 'asc' ? 'asc' : 'desc')->orderBy('id', 'desc');

        $page = $query->paginate(Statamic::cpPerPage($request->input('perPage')));

        return response()->json([
            'data' => collect($page->items())->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'title' => $transaction->name(),
                'reference' => $transaction->reference,
                'contact' => $transaction->contact?->name(),
                'contact_url' => $transaction->contact ? cp_route('radpack-crm.contacts.show', $transaction->contact) : null,
                'invoice' => $transaction->invoice?->number,
                'invoice_url' => $transaction->invoice ? cp_route('radpack-crm.invoices.show', $transaction->invoice) : null,
                'amount' => $transaction->money(),
                'type' => $transaction->type,
                'status' => $transaction->status,
                'source' => $transaction->source,
                'date' => $transaction->date?->format('Y-m-d'),
                'edit_url' => cp_route('radpack-crm.transactions.edit', $transaction),
            ])->all(),
            'meta' => [
                'columns' => ListingColumns::fromRequest($request, 'transactions'),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'links' => [],
        ]);
    }

    public function create(Request $request): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Transaction::blueprint())
            ->icon('money-cashier-price-tag')
            ->title(__('Create Transaction'))
            ->values([
                'contact' => $request->filled('contact') ? [$request->integer('contact')] : [],
                'currency' => Settings::currency(),
                'date' => today()->format('Y-m-d'),
                'type' => 'sale',
                'status' => 'succeeded',
            ])
            ->submittingTo(cp_route('radpack-crm.transactions.store'), 'POST');
    }

    /**
     * @return array{redirect: string}
     */
    public function store(Request $request): array
    {
        $this->authorize('edit crm');

        $transaction = (new Transaction)->fillFromBlueprint(PublishForm::make(Transaction::blueprint())->submit($request->all()));
        $transaction->save();

        return ['redirect' => cp_route('radpack-crm.transactions.index')];
    }

    public function edit(Transaction $transaction): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Transaction::blueprint())
            ->icon('money-cashier-price-tag')
            ->title($transaction->name())
            ->values($transaction->blueprintValues())
            ->submittingTo(cp_route('radpack-crm.transactions.update', $transaction));
    }

    /**
     * @return array{redirect: string}
     */
    public function update(Request $request, Transaction $transaction): array
    {
        $this->authorize('edit crm');

        $transaction->fillFromBlueprint(PublishForm::make(Transaction::blueprint())->submit($request->all()))->save();

        return ['redirect' => $transaction->invoice ? cp_route('radpack-crm.invoices.show', $transaction->invoice) : cp_route('radpack-crm.transactions.index')];
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete crm');

        $transaction->delete();

        return redirect()->to(cp_route('radpack-crm.transactions.index'));
    }
}
