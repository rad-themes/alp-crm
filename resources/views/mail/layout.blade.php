<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; color: #18181b; }
        .wrapper { width: 100%; background: #f4f4f5; padding: 32px 0; }
        .content { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; padding: 32px; font-size: 15px; line-height: 1.6; }
        .content a { color: #2563eb; }
        .content img { max-width: 100%; height: auto; }
        .footer { max-width: 600px; margin: 16px auto 0; text-align: center; font-size: 12px; color: #71717a; line-height: 1.5; }
        .footer a { color: #71717a; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="content">
        {!! $html !!}
    </div>
    <div class="footer">
        {{ $business['name'] }}@if ($business['address'])<br>{!! nl2br(e($business['address'])) !!}@endif
        @if ($unsubscribeUrl)
            <br><a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe') }}</a>
        @endif
    </div>
</div>
@if ($pixelUrl)
    <img src="{{ $pixelUrl }}" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px">
@endif
</body>
</html>
