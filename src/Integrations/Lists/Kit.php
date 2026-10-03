<?php

namespace RadThemes\AlpCrm\Integrations\Lists;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Settings;

/**
 * Kit (formerly ConvertKit), API v4.
 */
class Kit implements MailingList
{
    public static function label(): string
    {
        return 'Kit';
    }

    public static function configured(): bool
    {
        return (bool) Settings::secret('kit_api_key');
    }

    private static function api(): PendingRequest
    {
        return Http::withHeaders(['X-Kit-Api-Key' => (string) Settings::secret('kit_api_key')])->baseUrl('https://api.kit.com/v4')->acceptJson()->timeout(15);
    }

    public static function sync(Contact $contact): void
    {
        if (! $contact->isSubscribed()) {
            $id = self::api()->get('subscribers', ['email_address' => $contact->email])->json('subscribers.0.id');
            if ($id) {
                self::api()->withBody('{}', 'application/json')->post("subscribers/{$id}/unsubscribe")->throw();
            }

            return;
        }

        self::api()->post('subscribers', [
            'email_address' => $contact->email,
            'first_name' => $contact->first_name,
            'state' => 'active',
            'fields' => array_filter(['Last name' => $contact->last_name]),
        ])->throw();

        if ($formId = Settings::get('kit_form_id')) {
            self::api()->post("forms/{$formId}/subscribers", ['email_address' => $contact->email])->throw();
        }

        foreach ($contact->tags()->pluck('name') as $tag) {
            self::api()->post('tags/'.self::tagId($tag).'/subscribers', ['email_address' => $contact->email])->throw();
        }
    }

    private static function tagId(string $name): int
    {
        return Cache::remember('alp-crm.kit-tag.'.md5(mb_strtolower($name)), 86400, fn () => (int) self::api()->post('tags', ['name' => $name])->throw()->json('tag.id'));
    }
}
