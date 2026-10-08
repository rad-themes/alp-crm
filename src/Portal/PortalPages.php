<?php

namespace RadThemes\AlpCrm\Portal;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\File;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Models\Transaction;
use RadThemes\ClientPortal\Extensions;
use Statamic\Contracts\Auth\User;

/**
 * Adds "Billing" and "Files" pages to rad-themes/client-portal, for clients whose
 * user account is linked to a CRM contact.
 *
 * The link is always explicit — the contact's "Portal user" field. Matching on the
 * email address would be enough to see someone's billing on a site with open
 * registration, because Statamic doesn't verify email addresses.
 */
class PortalPages
{
    public static function register(): void
    {
        if (! class_exists(Extensions::class)) {
            return;
        }

        Extensions::page(
            'billing',
            __('Billing'),
            fn (User $user) => view('alp-crm::portal.billing', self::billing($user))->render(),
            fn (User $user) => self::documents(Invoice::query(), $user)->exists() || self::documents(Quote::query(), $user)->exists(),
        );

        Extensions::page(
            'files',
            __('Files'),
            fn (User $user) => view('alp-crm::portal.files', ['files' => self::files($user)->get()])->render(),
            fn (User $user) => self::files($user)->exists(),
        );
    }

    /**
     * @return Collection<int, Contact>
     */
    public static function contactsFor(User $user): Collection
    {
        return Contact::where('user_id', $user->id())->get();
    }

    public static function documents(Builder $query, User $user): Builder
    {
        $contacts = self::contactsFor($user);
        $companies = $contacts->pluck('company_id')->filter()->unique();

        return $query
            ->where(fn (Builder $q) => $q->whereIn('contact_id', $contacts->pluck('id')->all() ?: [0])->orWhereIn('company_id', $companies->all() ?: [0]))
            ->whereNotIn('status', ['draft', 'void']);
    }

    public static function files(User $user): Builder
    {
        $contacts = self::contactsFor($user);
        $companies = $contacts->pluck('company_id')->filter()->unique();

        return File::where('portal', true)
            ->where(fn (Builder $q) => $q->whereIn('contact_id', $contacts->pluck('id')->all() ?: [0])->orWhereIn('company_id', $companies->all() ?: [0]))
            ->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    private static function billing(User $user): array
    {
        $contacts = self::contactsFor($user);

        return [
            'invoices' => self::documents(Invoice::query(), $user)->latest('issue_date')->latest('id')->get(),
            'quotes' => self::documents(Quote::query(), $user)->latest('issue_date')->latest('id')->get(),
            'payments' => Transaction::where('status', 'succeeded')->whereIn('contact_id', $contacts->pluck('id')->all() ?: [0])->latest('date')->latest('id')->limit(50)->get(),
        ];
    }
}
