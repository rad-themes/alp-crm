@include('radpack-crm::portal._styles')
@php
    $badge = fn ($status) => match ($status) {
        'paid', 'accepted' => 'rp-badge rp-badge-green',
        'overdue', 'declined', 'expired' => 'rp-badge rp-badge-red',
        'sent', 'partial' => 'rp-badge rp-badge-blue',
        default => 'rp-badge',
    };
    $labels = \RadThemes\RadpackCrm\Support\Documents::statusLabels();
@endphp

<section class="rp-section">
    <h2>{{ __('Invoices') }}</h2>
    <div class="rp-card rp-scroll">
        @if ($invoices->isEmpty())
            <p class="rp-empty">{{ __('No invoices yet.') }}</p>
        @else
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th class="rp-hide-sm">{{ __('Date') }}</th>
                        <th class="rp-hide-sm">{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="rp-num">{{ __('Total') }}</th>
                        <th class="rp-num">{{ __('Balance') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        @php $status = $invoice->displayStatus(); @endphp
                        <tr>
                            <td><strong>{{ $invoice->number }}</strong>@if ($invoice->title)<br><span class="rp-muted">{{ $invoice->title }}</span>@endif</td>
                            <td class="rp-hide-sm">{{ $invoice->issue_date?->isoFormat('ll') }}</td>
                            <td class="rp-hide-sm">{{ $invoice->due_date?->isoFormat('ll') }}</td>
                            <td data-label="{{ __('Status') }}"><span class="{{ $badge($status) }}">{{ $labels[$status] ?? ucfirst($status) }}</span></td>
                            <td class="rp-num" data-label="{{ __('Total') }}">{{ $invoice->money($invoice->total) }}</td>
                            <td class="rp-num" data-label="{{ __('Balance') }}">{{ $invoice->money($invoice->balance()) }}</td>
                            <td>
                                <div class="rp-actions">
                                    @if ($invoice->isPayable() && $invoice->balance() > 0)
                                        <a class="rp-button" href="{{ \RadThemes\RadpackCrm\Support\Documents::publicUrl($invoice) }}">{{ __('View & pay') }}</a>
                                    @else
                                        <a class="rp-link" href="{{ \RadThemes\RadpackCrm\Support\Documents::publicUrl($invoice) }}">{{ __('View') }}</a>
                                    @endif
                                    <a class="rp-link" href="{{ route('statamic.radpack-crm.public.invoice.pdf', $invoice->token) }}">PDF</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>

<section class="rp-section">
    <h2>{{ __('Quotes') }}</h2>
    <div class="rp-card rp-scroll">
        @if ($quotes->isEmpty())
            <p class="rp-empty">{{ __('No quotes yet.') }}</p>
        @else
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>{{ __('Quote') }}</th>
                        <th class="rp-hide-sm">{{ __('Date') }}</th>
                        <th class="rp-hide-sm">{{ __('Valid until') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="rp-num">{{ __('Total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotes as $quote)
                        @php $status = $quote->displayStatus(); @endphp
                        <tr>
                            <td><strong>{{ $quote->number }}</strong>@if ($quote->title)<br><span class="rp-muted">{{ $quote->title }}</span>@endif</td>
                            <td class="rp-hide-sm">{{ $quote->issue_date?->isoFormat('ll') }}</td>
                            <td class="rp-hide-sm">{{ $quote->valid_until?->isoFormat('ll') }}</td>
                            <td data-label="{{ __('Status') }}"><span class="{{ $badge($status) }}">{{ $labels[$status] ?? ucfirst($status) }}</span></td>
                            <td class="rp-num" data-label="{{ __('Total') }}">{{ $quote->money($quote->total) }}</td>
                            <td>
                                <div class="rp-actions">
                                    @if ($quote->canBeRespondedTo())
                                        <a class="rp-button" href="{{ \RadThemes\RadpackCrm\Support\Documents::publicUrl($quote) }}">{{ __('Review') }}</a>
                                    @else
                                        <a class="rp-link" href="{{ \RadThemes\RadpackCrm\Support\Documents::publicUrl($quote) }}">{{ __('View') }}</a>
                                    @endif
                                    <a class="rp-link" href="{{ route('statamic.radpack-crm.public.quote.pdf', $quote->token) }}">PDF</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>

@if ($payments->isNotEmpty())
    <section class="rp-section">
        <h2>{{ __('Payments') }}</h2>
        <div class="rp-card rp-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="rp-num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="rp-muted">{{ $payment->date?->isoFormat('ll') }}</td>
                            <td>{{ $payment->title ?: $payment->reference }}@if ($payment->type === 'refund') <span class="rp-badge">{{ __('Refund') }}</span>@endif</td>
                            <td class="rp-num">{{ $payment->type === 'refund' ? '−' : '' }}{{ $payment->money() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
