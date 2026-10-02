<?php

namespace RadThemes\RadpackCrm\Integrations\Lists;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Support\Settings;

class Mailchimp implements MailingList
{
    public static function label(): string
    {
        return 'Mailchimp';
    }

    public static function configured(): bool
    {
        return str_contains((string) Settings::secret('mailchimp_api_key'), '-') && Settings::get('mailchimp_list_id');
    }

    private static function api(): PendingRequest
    {
        $key = (string) Settings::secret('mailchimp_api_key');
        $dc = substr($key, strrpos($key, '-') + 1);

        return Http::withBasicAuth('radpack', $key)->baseUrl("https://{$dc}.api.mailchimp.com/3.0")->acceptJson()->timeout(15);
    }

    public static function sync(Contact $contact): void
    {
        $list = Settings::get('mailchimp_list_id');
        $hash = md5(mb_strtolower($contact->email));
        $subscribed = $contact->isSubscribed();

        self::api()->put("lists/{$list}/members/{$hash}", array_filter([
            'email_address' => $contact->email,
            'status_if_new' => $subscribed ? (Settings::get('mailchimp_double_optin') ? 'pending' : 'subscribed') : 'unsubscribed',
            'status' => $subscribed ? null : 'unsubscribed',
            'merge_fields' => array_filter(['FNAME' => $contact->first_name, 'LNAME' => $contact->last_name, 'PHONE' => $contact->phone]),
        ], fn ($value) => $value !== null))->throw();

        if ($tags = $contact->tags()->pluck('name')->all()) {
            self::api()->post("lists/{$list}/members/{$hash}/tags", [
                'tags' => array_map(fn ($tag) => ['name' => $tag, 'status' => 'active'], $tags),
            ])->throw();
        }
    }
}
