<x-mail::message>
@if ($personalMessage)
{{ $personalMessage }}

@else
@if ($isInvoice)
{{ __('Hi :name, here is invoice :number for :total.', ['name' => $document->contact?->first_name ?: __('there'), 'number' => $document->number, 'total' => $document->money($document->total)]) }}
@else
{{ __('Hi :name, here is quote :number for :total.', ['name' => $document->contact?->first_name ?: __('there'), 'number' => $document->number, 'total' => $document->money($document->total)]) }}
@endif

@endif
@if ($document->title)
**{{ $document->title }}**

@endif
@if ($isInvoice && $document->due_date)
**{{ __('Due date') }}:** {{ $document->due_date->isoFormat('LL') }}
@elseif (! $isInvoice && $document->valid_until)
**{{ __('Valid until') }}:** {{ $document->valid_until->isoFormat('LL') }}
@endif

<x-mail::button :url="$url">
{{ $isInvoice ? __('View invoice') : __('View and respond to quote') }}
</x-mail::button>

{{ __('The PDF is attached.') }}

{{ $business['name'] }}
</x-mail::message>
