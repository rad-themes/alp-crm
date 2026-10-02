<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Mail\DocumentMail;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Support\Documents;
use RadThemes\RadpackCrm\Support\ListingColumns;
use RadThemes\RadpackCrm\Support\Presenter;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Statamic;

/**
 * Shared Control Panel behaviour for quotes and invoices.
 */
abstract class DocumentsController extends CpController
{
    /**
     * @return class-string<Quote|Invoice>
     */
    abstract protected function model(): string;

    abstract protected function type(): string;

    abstract protected function plural(): string;

    abstract protected function secondDateColumn(): string;

    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Documents/Index', [
            'type' => $this->type(),
            'title' => $this->type() === 'invoice' ? __('Invoices') : __('Quotes'),
            'columns' => ListingColumns::for('documents', $this->type()),
            'jsonUrl' => cp_route("radpack-crm.{$this->plural()}.json"),
            'createUrl' => cp_route("radpack-crm.{$this->plural()}.create"),
            'statuses' => $this->statusOptions(),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $this->authorize('view crm');

        $query = $this->model()::query()->with(['contact', 'company']);

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($contact) => $contact->search($search))
                ->orWhereHas('company', fn ($company) => $company->search($search)));
        }

        if ($status = $request->input('status')) {
            $status === 'overdue' && $this->type() === 'invoice'
                ? $query->overdue()
                : $query->where('status', $status);
        }

        $sort = in_array($request->input('sort'), ['number', 'issue_date', $this->secondDateColumn(), 'total', 'status'], true) ? $request->input('sort') : 'issue_date';
        $query->orderBy($sort, $request->input('order') === 'asc' ? 'asc' : 'desc')->orderBy('id', 'desc');

        $page = $query->paginate(Statamic::cpPerPage($request->input('perPage')));

        return response()->json([
            'data' => collect($page->items())->map(fn ($document) => array_merge(Documents::toArray($document), [
                'show_url' => cp_route("radpack-crm.{$this->plural()}.show", $document),
                'edit_url' => cp_route("radpack-crm.{$this->plural()}.edit", $document),
            ]))->all(),
            'meta' => [
                'columns' => ListingColumns::fromRequest($request, 'documents', $this->type()),
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

    public function create(Request $request): Response
    {
        $this->authorize('edit crm');

        $model = $this->model();
        $contact = $request->filled('contact') ? Contact::find($request->integer('contact')) : null;
        $source = $request->filled('duplicate') ? $model::find($request->integer('duplicate')) : null;

        return $this->editor(new $model([
            'contact_id' => $source->contact_id ?? $contact?->id,
            'company_id' => $source->company_id ?? $contact?->company_id,
            'title' => $source?->title,
            'currency' => $source?->currency ?? Settings::currency(),
            'discount' => $source?->discount ?? 0,
            'notes' => $source?->notes,
        ]), $source?->items->map->toEditorArray()->all() ?? []);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit crm');

        $document = $this->model()::create($this->validatedAttributes($request));
        $document->syncItems($request->input('items', []), (float) $request->input('discount', 0));

        return redirect()->to(cp_route("radpack-crm.{$this->plural()}.show", $document))->with('success', __('Saved'));
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit crm');

        $document = $this->model()::with('items')->findOrFail($id);

        return $this->editor($document, $document->items->map->toEditorArray()->all());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $document = $this->model()::findOrFail($id);
        $document->update($this->validatedAttributes($request, $document));
        $document->syncItems($request->input('items', []), (float) $request->input('discount', 0));

        return redirect()->to(cp_route("radpack-crm.{$this->plural()}.show", $document));
    }

    public function show(int $id): Response
    {
        $this->authorize('view crm');

        $document = $this->model()::with(['items', 'contact', 'company'])->findOrFail($id);

        return Inertia::render('radpack-crm::Documents/Show', array_merge([
            'document' => Documents::toArray($document),
            'activities' => Presenter::activities($document->activities),
            'urls' => $this->urls($document),
            'canEdit' => User::current()->can('edit crm'),
            'canDelete' => User::current()->can('delete crm'),
        ], $this->extraShowProps($document)));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete crm');

        $this->model()::findOrFail($id)->delete();

        return redirect()->to(cp_route("radpack-crm.{$this->plural()}.index"));
    }

    public function send(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $document = $this->model()::with(['items', 'contact', 'company'])->findOrFail($id);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        Mail::to($data['to'])->send(new DocumentMail($document, $data['message'] ?? null));

        $document->update([
            'sent_at' => now(),
            'status' => $document->status === 'draft' ? 'sent' : $document->status,
        ]);
        $document->logActivity('sent', __('Emailed to :email', ['email' => $data['to']]));
        $document->contact?->logActivity($this->type().'_sent', __(':number emailed', ['number' => $document->number]));

        return back()->with('success', __('Sent'));
    }

    public function markSent(int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $document = $this->model()::findOrFail($id);

        if ($document->status === 'draft') {
            $document->update(['status' => 'sent', 'sent_at' => $document->sent_at ?? now()]);
        }

        return back();
    }

    public function pdf(int $id): HttpResponse
    {
        $this->authorize('view crm');

        $document = $this->model()::findOrFail($id);

        return response(Documents::pdf($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Documents::filename($document).'"',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function editor(Quote|Invoice $document, array $items): Response
    {
        $exists = $document->exists;

        return Inertia::render('radpack-crm::Documents/Edit', [
            'type' => $this->type(),
            'title' => $exists ? $document->number : ($this->type() === 'invoice' ? __('Create Invoice') : __('Create Quote')),
            'values' => [
                'contact_id' => $document->contact_id,
                'title' => $document->title,
                'currency' => $document->currency ?? Settings::currency(),
                'issue_date' => ($document->issue_date ?? today())->format('Y-m-d'),
                'second_date' => $document->{$this->secondDateColumn()}?->format('Y-m-d')
                    ?? today()->addDays((int) Settings::get($this->type() === 'invoice' ? 'payment_terms_days' : 'quote_valid_days'))->format('Y-m-d'),
                'discount' => (float) ($document->discount ?? 0),
                'notes' => $document->notes,
                'terms' => $document->terms ?? Settings::get($this->type() === 'invoice' ? 'invoice_terms' : 'quote_terms'),
                'items' => $items ?: [['description' => '', 'quantity' => 1, 'unit_price' => 0, 'tax_name' => null, 'tax_rate' => 0]],
            ],
            'contact' => $document->contact ? ['value' => $document->contact->id, 'label' => $document->contact->name()] : null,
            'currencies' => collect(Settings::currencyOptions())->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values(),
            'taxRates' => Settings::taxRates(),
            'pricesIncludeTax' => (bool) Settings::get('prices_include_tax'),
            'contactSearchUrl' => cp_route('radpack-crm.contacts.search'),
            'submitUrl' => $exists ? cp_route("radpack-crm.{$this->plural()}.update", $document) : cp_route("radpack-crm.{$this->plural()}.store"),
            'submitMethod' => $exists ? 'patch' : 'post',
            'cancelUrl' => $exists ? cp_route("radpack-crm.{$this->plural()}.show", $document) : cp_route("radpack-crm.{$this->plural()}.index"),
            'secondDateLabel' => $this->type() === 'invoice' ? __('Due date') : __('Valid until'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedAttributes(Request $request, Quote|Invoice|null $document = null): array
    {
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', Rule::exists('crm_contacts', 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'issue_date' => ['required', 'date'],
            'second_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'items' => ['array', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['required_with:items.*.description', 'numeric'],
            'items.*.unit_price' => ['required_with:items.*.description', 'numeric'],
            'items.*.tax_name' => ['nullable', 'string', 'max:255'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $contact = isset($data['contact_id']) ? Contact::find($data['contact_id']) : null;

        return [
            'contact_id' => $contact?->id,
            'company_id' => $contact?->company_id ?? $document?->company_id,
            'title' => $data['title'] ?? null,
            'currency' => strtoupper($data['currency']),
            'issue_date' => $data['issue_date'],
            $this->secondDateColumn() => $data['second_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        $statuses = $this->type() === 'invoice' ? [...Invoice::STATUSES, 'overdue'] : Quote::STATUSES;

        return collect($statuses)->map(fn ($status) => ['value' => $status, 'label' => Documents::statusLabels()[$status]])->all();
    }

    /**
     * @return array<string, string>
     */
    protected function urls(Quote|Invoice $document): array
    {
        return [
            'index' => cp_route("radpack-crm.{$this->plural()}.index"),
            'edit' => cp_route("radpack-crm.{$this->plural()}.edit", $document),
            'destroy' => cp_route("radpack-crm.{$this->plural()}.destroy", $document),
            'send' => cp_route("radpack-crm.{$this->plural()}.send", $document),
            'markSent' => cp_route("radpack-crm.{$this->plural()}.mark-sent", $document),
            'pdf' => cp_route("radpack-crm.{$this->plural()}.pdf", $document),
            'duplicate' => cp_route("radpack-crm.{$this->plural()}.create", ['duplicate' => $document->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraShowProps(Quote|Invoice $document): array
    {
        return [];
    }
}
