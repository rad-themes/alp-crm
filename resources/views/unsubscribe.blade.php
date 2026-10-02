<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Unsubscribe') }} · {{ $business['name'] }}</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; background: #f4f4f5; color: #18181b; }
        main { max-width: 440px; margin: 15vh auto 0; background: #fff; border-radius: 12px; padding: 32px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { color: #52525b; line-height: 1.5; }
        button { margin-top: 12px; border: 0; border-radius: 8px; padding: 10px 18px; font-size: 15px; font-weight: 600; background: #18181b; color: #fff; cursor: pointer; }
    </style>
</head>
<body>
<main>
    @if ($done)
        <h1>{{ __('You’re unsubscribed') }}</h1>
        <p>{{ __(':email won’t receive marketing emails from :business any more.', ['email' => $recipient->email, 'business' => $business['name']]) }}</p>
    @else
        <h1>{{ __('Unsubscribe?') }}</h1>
        <p>{{ __('Stop marketing emails from :business to :email.', ['business' => $business['name'], 'email' => $recipient->email]) }}</p>
        <form method="POST" action="{{ route('statamic.radpack-crm.unsubscribe.confirm', $recipient->token) }}">
            <button type="submit">{{ __('Unsubscribe') }}</button>
        </form>
    @endif
</main>
</body>
</html>
