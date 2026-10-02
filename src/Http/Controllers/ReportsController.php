<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Support\Money;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Sales dashboard and status funnel.
 */
class ReportsController extends CpController
{
    private const PERIODS = ['30d' => 30, '90d' => 90, '12m' => 365, 'all' => null];

    public function __invoke(Request $request): Response
    {
        $this->authorize('view crm');

        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '90d';
        $from = self::PERIODS[$period] ? today()->subDays(self::PERIODS[$period] - 1) : null;
        $currency = Settings::currency();

        $sales = Transaction::where('status', 'succeeded')->where('currency', $currency)
            ->when($from, fn ($query) => $query->where('date', '>=', $from));

        $responded = Quote::whereIn('status', ['accepted', 'declined'])->when($from, fn ($query) => $query->where('responded_at', '>=', $from));
        $accepted = (clone $responded)->where('status', 'accepted')->count();
        $respondedCount = $responded->count();

        $paid = Invoice::where('status', 'paid')->whereNotNull('paid_at')->when($from, fn ($query) => $query->where('paid_at', '>=', $from))->get(['issue_date', 'paid_at']);

        return Inertia::render('radpack-crm::Reports', [
            'period' => $period,
            'periods' => [
                ['value' => '30d', 'label' => __('Last 30 days')],
                ['value' => '90d', 'label' => __('Last 90 days')],
                ['value' => '12m', 'label' => __('Last 12 months')],
                ['value' => 'all', 'label' => __('All time')],
            ],
            'kpis' => [
                ['label' => __('Revenue'), 'value' => Money::format(Transaction::revenue($sales), $currency)],
                ['label' => __('New contacts'), 'value' => Contact::when($from, fn ($query) => $query->where('created_at', '>=', $from))->count()],
                ['label' => __('Quotes accepted'), 'value' => $respondedCount ? round($accepted / $respondedCount * 100).'%' : '—', 'hint' => __(':accepted of :total answered', ['accepted' => $accepted, 'total' => $respondedCount])],
                ['label' => __('Average days to pay'), 'value' => $paid->count() ? (string) round($paid->avg(fn ($invoice) => $invoice->issue_date ? $invoice->issue_date->diffInDays($invoice->paid_at) : 0)) : '—'],
                ['label' => __('Outstanding'), 'value' => Money::format((float) Invoice::outstanding()->where('currency', $currency)->get()->sum->balance(), $currency)],
                ['label' => __('Overdue invoices'), 'value' => Invoice::overdue()->count()],
            ],
            'funnel' => $this->funnel(),
            'months' => $this->months($currency),
            'topCustomers' => $this->topCustomers($from, $currency),
            'sources' => (clone $sales)->where('type', 'sale')->get(['source', 'amount'])
                ->groupBy(fn ($transaction) => $transaction->source ?: 'manual')
                ->map(fn ($group, $source) => ['source' => ucfirst($source), 'total' => (float) $group->sum('amount'), 'formatted' => Money::format((float) $group->sum('amount'), $currency)])
                ->sortByDesc('total')->values(),
        ]);
    }

    /**
     * Contacts at each funnel stage (statuses in order), and how many reached at least that stage.
     *
     * @return array<int, array{status: string, label: string, count: int, reached: int, rate: int}>
     */
    private function funnel(): array
    {
        $options = (array) (Contact::blueprint()->field('status')?->get('options') ?? []);
        $stages = collect((array) Settings::get('funnel_statuses', []))->filter(fn ($status) => isset($options[$status]))->values();

        if ($stages->isEmpty()) {
            $stages = collect(array_keys($options))->reject(fn ($status) => in_array($status, ['refused', 'blacklisted'], true))->values();
        }

        $counts = Contact::whereIn('status', $stages)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $reached = 0;
        $rows = [];

        foreach ($stages->reverse() as $status) {
            $reached += (int) ($counts[$status] ?? 0);
            $rows[] = ['status' => $status, 'label' => $options[$status], 'count' => (int) ($counts[$status] ?? 0), 'reached' => $reached];
        }

        $rows = array_reverse($rows);
        $top = max(1, $rows[0]['reached'] ?? 1);

        return array_map(fn ($row) => $row + ['rate' => (int) round($row['reached'] / $top * 100)], $rows);
    }

    /**
     * Revenue and new contacts for the last 12 months.
     *
     * @return array<int, array{month: string, label: string, revenue: float, revenue_formatted: string, contacts: int}>
     */
    private function months(string $currency): array
    {
        $start = today()->startOfMonth()->subMonths(11);

        // ponytail: grouped in PHP for portability across SQLite/MySQL/Postgres; fine for tens of thousands of rows.
        $revenue = Transaction::where('status', 'succeeded')->where('currency', $currency)->where('date', '>=', $start)
            ->get(['date', 'type', 'amount'])
            ->groupBy(fn ($transaction) => $transaction->date->format('Y-m'))
            ->map(fn ($group) => $group->sum(fn ($transaction) => $transaction->type === 'refund' ? -$transaction->amount : $transaction->amount));

        $contacts = Contact::where('created_at', '>=', $start)->pluck('created_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        return collect(range(0, 11))->map(function ($i) use ($start, $revenue, $contacts, $currency) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            return [
                'month' => $key,
                'label' => $month->isoFormat('MMM'),
                'revenue' => Money::round((float) ($revenue[$key] ?? 0)),
                'revenue_formatted' => Money::format((float) ($revenue[$key] ?? 0), $currency),
                'contacts' => (int) ($contacts[$key] ?? 0),
            ];
        })->all();
    }

    /**
     * @return array<int, array{name: string, url: string, total: string}>
     */
    private function topCustomers(?Carbon $from, string $currency): array
    {
        return Transaction::where('status', 'succeeded')->where('currency', $currency)->whereNotNull('contact_id')
            ->when($from, fn ($query) => $query->where('date', '>=', $from))
            ->get(['contact_id', 'type', 'amount'])
            ->groupBy('contact_id')
            ->map(fn ($group) => $group->sum(fn ($transaction) => $transaction->type === 'refund' ? -$transaction->amount : $transaction->amount))
            ->sortDesc()->take(5)
            ->map(fn ($total, $contactId) => ($contact = Contact::find($contactId)) ? [
                'name' => $contact->name(),
                'url' => cp_route('radpack-crm.contacts.show', $contact),
                'total' => Money::format((float) $total, $currency),
            ] : null)
            ->filter()->values()->all();
    }
}
