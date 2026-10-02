@php
    /** @var \RadThemes\RadpackCrm\Models\Quote|\RadThemes\RadpackCrm\Models\Invoice $document */
    $isInvoice = $type === 'invoice';
    $status = $document->displayStatus();
    $client = $document->contact;
    $company = $document->company;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $isInvoice ? __('Invoice') : __('Quote') }} {{ $document->number }} · {{ $business['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #18181b; font-size: 13px; line-height: 1.5; background: {{ $forPdf ? '#fff' : '#f4f4f5' }}; }
        .page { max-width: 800px; margin: {{ $forPdf ? '0' : '32px auto' }}; background: #fff; padding: {{ $forPdf ? '0' : '48px' }}; {{ $forPdf ? '' : 'border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08);' }} }
        table { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: top; }
        .muted { color: #71717a; }
        h1 { font-size: 26px; margin: 0 0 4px; letter-spacing: -0.01em; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; background: #f4f4f5; color: #3f3f46; }
        .badge.paid, .badge.accepted { background: #dcfce7; color: #166534; }
        .badge.overdue, .badge.declined, .badge.void { background: #fee2e2; color: #991b1b; }
        .badge.partial, .badge.sent { background: #dbeafe; color: #1e40af; }
        .meta td { padding: 2px 0; }
        .items { margin-top: 32px; }
        .items th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #71717a; border-bottom: 1px solid #e4e4e7; padding: 8px 6px; }
        .items td { border-bottom: 1px solid #f4f4f5; padding: 10px 6px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 16px; font-weight: bold; border-top: 2px solid #18181b; padding-top: 8px; }
        .block { margin-top: 28px; white-space: pre-line; }
        .actions { margin-top: 32px; display: flex; gap: 12px; flex-wrap: wrap; }
        .button { display: inline-block; border: 0; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; background: #18181b; color: #fff; }
        .button.secondary { background: #f4f4f5; color: #18181b; }
        .notice { margin-top: 24px; padding: 12px 16px; border-radius: 8px; background: #f0fdf4; color: #166534; }
        .notice.error { background: #fef2f2; color: #991b1b; }
        @media print { body { background: #fff; } .page { margin: 0; box-shadow: none; padding: 0; } .actions, .notice { display: none; } }
        @media (max-width: 600px) { .page { padding: 24px; margin: 0; border-radius: 0; } .top td { display: block; width: 100% !important; text-align: left !important; } }
    </style>
</head>
<body>
<div class="page">
    <table class="top">
        <tr>
            <td style="width: 55%">
                @if ($business['logo'])
                    <img src="{{ $business['logo'] }}" alt="{{ $business['name'] }}" style="max-height: 56px; max-width: 200px; margin-bottom: 12px;">
                @else
                    <div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">{{ $business['name'] }}</div>
                @endif
                <div class="muted" style="white-space: pre-line">{{ $business['address'] }}</div>
                <div class="muted">{{ collect([$business['email'], $business['phone']])->filter()->implode(' · ') }}</div>
                @if ($business['tax_number'])
                    <div class="muted">{{ __('Tax number') }}: {{ $business['tax_number'] }}</div>
                @endif
            </td>
            <td style="width: 45%; text-align: right">
                <h1>{{ $isInvoice ? __('Invoice') : __('Quote') }}</h1>
                <div style="font-size: 15px; font-weight: bold">{{ $document->number }}</div>
                <div style="margin-top: 6px"><span class="badge {{ $status }}">{{ $labels[$status] ?? $status }}</span></div>
            </td>
        </tr>
    </table>

    <table class="top" style="margin-top: 32px">
        <tr>
            <td style="width: 55%">
                <div class="muted" style="font-size: 11px; text-transform: uppercase; letter-spacing: .04em">{{ $isInvoice ? __('Bill to') : __('Prepared for') }}</div>
                <div style="font-weight: bold">{{ $document->clientName() }}</div>
                @if ($client && $company)
                    <div>{{ $company->name }}</div>
                @endif
                @php($address = collect([$client?->data['address_line_1'] ?? $company?->data['address_line_1'] ?? null, $client?->data['address_line_2'] ?? $company?->data['address_line_2'] ?? null, trim(($client?->data['city'] ?? $company?->data['city'] ?? '').' '.($client?->data['postcode'] ?? $company?->data['postcode'] ?? ''))])->filter())
                @foreach ($address as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
                @if ($document->clientEmail())
                    <div class="muted">{{ $document->clientEmail() }}</div>
                @endif
            </td>
            <td style="width: 45%">
                <table class="meta">
                    <tr><td class="muted">{{ __('Issued') }}</td><td class="num">{{ $document->issue_date?->isoFormat('LL') }}</td></tr>
                    @if ($isInvoice && $document->due_date)
                        <tr><td class="muted">{{ __('Due') }}</td><td class="num">{{ $document->due_date->isoFormat('LL') }}</td></tr>
                    @elseif (! $isInvoice && $document->valid_until)
                        <tr><td class="muted">{{ __('Valid until') }}</td><td class="num">{{ $document->valid_until->isoFormat('LL') }}</td></tr>
                    @endif
                    @if ($isInvoice)
                        <tr><td class="muted">{{ __('Balance due') }}</td><td class="num"><strong>{{ $document->money($document->balance()) }}</strong></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($document->title)
        <div style="margin-top: 28px; font-size: 15px; font-weight: bold">{{ $document->title }}</div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Price') }}</th>
                <th class="num">{{ __('Tax') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->items as $item)
                <tr>
                    <td style="white-space: pre-line">{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $document->money($item->unit_price) }}</td>
                    <td class="num">{{ $item->tax_rate > 0 ? ($item->tax_name ?: '').' '.rtrim(rtrim(number_format($item->tax_rate, 3, '.', ''), '0'), '.').'%' : '—' }}</td>
                    <td class="num">{{ $document->money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">{{ __('Subtotal') }}</td><td class="num">{{ $document->money($document->subtotal) }}</td></tr>
        @if ($document->discount > 0)
            <tr><td class="muted">{{ __('Discount') }}</td><td class="num">−{{ $document->money($document->discount) }}</td></tr>
        @endif
        @if ($document->tax_total > 0)
            <tr><td class="muted">{{ __('Tax') }}</td><td class="num">{{ $document->money($document->tax_total) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('Total') }}</td><td class="num">{{ $document->money($document->total) }}</td></tr>
        @if ($isInvoice && $document->amount_paid > 0)
            <tr><td class="muted">{{ __('Paid') }}</td><td class="num">−{{ $document->money($document->amount_paid) }}</td></tr>
            <tr><td><strong>{{ __('Balance due') }}</strong></td><td class="num"><strong>{{ $document->money($document->balance()) }}</strong></td></tr>
        @endif
    </table>

    @if ($document->notes)
        <div class="block">{{ $document->notes }}</div>
    @endif
    @if ($document->terms)
        <div class="block muted">{{ $document->terms }}</div>
    @endif

    @unless ($forPdf)
        @if (session('radpack_crm_status'))
            <div class="notice">{{ session('radpack_crm_status') }}</div>
        @endif

        <div class="actions">
            @if (! $isInvoice && $document->canBeRespondedTo())
                <form method="POST" action="{{ route('statamic.radpack-crm.public.quote.respond', $document->token) }}">
                    @csrf
                    <input type="hidden" name="accepted" value="1">
                    <button class="button" type="submit">{{ __('Accept quote') }}</button>
                </form>
                <form method="POST" action="{{ route('statamic.radpack-crm.public.quote.respond', $document->token) }}">
                    @csrf
                    <input type="hidden" name="accepted" value="0">
                    <button class="button secondary" type="submit">{{ __('Decline') }}</button>
                </form>
            @endif
            <a class="button secondary" href="{{ route('statamic.radpack-crm.public.'.$type.'.pdf', $document->token) }}">{{ __('Download PDF') }}</a>
        </div>
    @endunless
</div>
</body>
</html>
