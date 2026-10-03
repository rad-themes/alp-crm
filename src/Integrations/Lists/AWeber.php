<?php

namespace RadThemes\AlpCrm\Integrations\Lists;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Settings;
use RadThemes\AlpCrm\Support\TokenStore;
use RuntimeException;

/**
 * AWeber, via OAuth 2. Connect from CRM → Integrations; tokens are refreshed automatically.
 */
class AWeber implements MailingList
{
    public const AUTHORIZE_URL = 'https://auth.aweber.com/oauth2/authorize';

    public const TOKEN_URL = 'https://auth.aweber.com/oauth2/token';

    public static function label(): string
    {
        return 'AWeber';
    }

    public static function configured(): bool
    {
        return TokenStore::get('aweber_refresh_token') && Settings::get('aweber_list_id');
    }

    public static function authorizeUrl(string $redirect, string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => Settings::secret('aweber_client_id'),
            'redirect_uri' => $redirect,
            'scope' => 'account.read list.read subscriber.read subscriber.write',
            'state' => $state,
        ]);
    }

    public static function connect(string $code, string $redirect): void
    {
        self::storeTokens(self::tokenRequest(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect]));

        TokenStore::put('aweber_account_id', self::api()->get('accounts')->throw()->json('entries.0.id'));
    }

    private static function tokenRequest(array $params): array
    {
        return Http::asForm()
            ->withBasicAuth((string) Settings::secret('aweber_client_id'), (string) Settings::secret('aweber_client_secret'))
            ->post(self::TOKEN_URL, $params)->throw()->json();
    }

    private static function storeTokens(array $tokens): void
    {
        TokenStore::put('aweber_refresh_token', $tokens['refresh_token']);
        Cache::put('alp-crm.aweber-access', $tokens['access_token'], max(60, (int) ($tokens['expires_in'] ?? 3600) - 120));
    }

    private static function api(): PendingRequest
    {
        $token = Cache::get('alp-crm.aweber-access');

        if (! $token) {
            $refresh = TokenStore::get('aweber_refresh_token') ?? throw new RuntimeException('AWeber is not connected');
            // AWeber rotates refresh tokens, so store the new one each time.
            $tokens = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]);
            self::storeTokens($tokens);
            $token = $tokens['access_token'];
        }

        return Http::withToken($token)->baseUrl('https://api.aweber.com/1.0')->acceptJson()->timeout(15);
    }

    public static function sync(Contact $contact): void
    {
        $base = 'accounts/'.TokenStore::get('aweber_account_id').'/lists/'.Settings::get('aweber_list_id').'/subscribers';
        $tags = $contact->tags()->pluck('name')->map(fn ($tag) => mb_strtolower($tag))->all();
        $existing = self::api()->get($base, ['ws.op' => 'find', 'email' => $contact->email])->json('entries.0');

        if ($existing) {
            self::api()->patch($base.'?subscriber_email='.urlencode($contact->email), [
                'name' => $contact->name(),
                'status' => $contact->isSubscribed() ? 'subscribed' : 'unsubscribed',
                'tags' => ['add' => $tags],
            ])->throw();
        } elseif ($contact->isSubscribed()) {
            self::api()->post($base, ['email' => $contact->email, 'name' => $contact->name(), 'tags' => $tags, 'update_existing' => 'true'])->throw();
        }
    }
}
