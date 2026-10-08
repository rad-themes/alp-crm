<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use RadThemes\AlpCrm\Database\Factories\ContactFactory;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Concerns\FiresCrmEvents;
use RadThemes\AlpCrm\Models\Concerns\HasBlueprint;
use RadThemes\AlpCrm\Models\Concerns\HasTags;
use RadThemes\AlpCrm\Models\Concerns\LogsActivity;
use RadThemes\AlpCrm\Support\Settings;
use Statamic\Facades\User;

/**
 * @property int $id
 * @property string $status
 * @property ?string $first_name
 * @property ?string $last_name
 * @property ?string $email
 * @property ?string $phone
 * @property ?int $company_id
 * @property ?string $owner_id
 * @property ?string $user_id
 * @property ?array<string, mixed> $data
 */
class Contact extends Model
{
    use FiresCrmEvents, HasBlueprint, HasFactory, HasTags, LogsActivity;

    protected $table = 'crm_contacts';

    protected $guarded = ['id'];

    /**
     * Alias emails to sync once saved.
     *
     * @var array<int, string>|null
     */
    protected ?array $pendingAliases = null;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'last_contacted_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Contact $contact) {
            File::where('contact_id', $contact->id)->get()->each->delete();
            Password::where('contact_id', $contact->id)->delete();
            $contact->notes()->delete();

            // Keep sales records and tasks, unlinked (foreign keys aren't enforced on every database).
            foreach ([Task::class, Quote::class, Invoice::class, Transaction::class] as $model) {
                $model::where('contact_id', $contact->id)->update(['contact_id' => null]);
            }
        });

        static::updated(function (Contact $contact) {
            if ($contact->wasChanged('status')) {
                CrmEvent::fire('contact.status_changed', $contact, ['from' => $contact->getOriginal('status'), 'to' => $contact->status]);
            }
        });

        static::saved(function (Contact $contact) {
            if ($contact->pendingAliases !== null) {
                $contact->aliases()->delete();
                $contact->aliases()->createMany(array_map(fn ($email) => ['email' => $email], $contact->pendingAliases));
                $contact->pendingAliases = null;
            }
        });
    }

    public static function crmEventType(): string
    {
        return 'contact';
    }

    public static function blueprintHandle(): string
    {
        return 'contact';
    }

    protected function blueprintColumns(): array
    {
        return ['status', 'first_name', 'last_name', 'email', 'phone'];
    }

    protected function relationBlueprintHandles(): array
    {
        return ['company', 'owner', 'portal_user', 'tags', 'aliases'];
    }

    protected function relationBlueprintValues(): array
    {
        return [
            'company' => $this->company_id ? [$this->company_id] : [],
            'owner' => $this->owner_id ? [$this->owner_id] : [],
            'portal_user' => $this->user_id ? [$this->user_id] : [],
            'tags' => $this->tags->pluck('name')->all(),
            'aliases' => $this->aliases->pluck('email')->all(),
        ];
    }

    protected function fillRelationsFromBlueprint(array $values): void
    {
        if (array_key_exists('company', $values)) {
            $this->company_id = collect($values['company'])->first();
        }

        if (array_key_exists('owner', $values)) {
            $this->owner_id = collect($values['owner'])->first();
        }

        if (array_key_exists('portal_user', $values)) {
            $this->user_id = collect($values['portal_user'])->first();
        }

        if (array_key_exists('tags', $values)) {
            $this->setTagsLater((array) $values['tags']);
        }

        if (array_key_exists('aliases', $values)) {
            $this->pendingAliases = collect((array) $values['aliases'])
                ->map(fn ($email) => mb_strtolower(trim((string) $email)))
                ->filter(fn ($email) => $email !== '' && $email !== mb_strtolower((string) $this->email))
                ->unique()->values()->all();
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ContactAlias::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest('date')->latest('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest('issue_date')->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issue_date')->latest('id');
    }

    /**
     * Lifetime value: succeeded sales minus refunds, in the default currency.
     */
    public function lifetimeValue(): float
    {
        return Transaction::revenue($this->transactions()->getQuery()->reorder()->where('currency', Settings::currency()));
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderByRaw('completed_at is not null')->orderByRaw('starts_at is null')->orderBy('starts_at');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class)->latest()->latest('id');
    }

    /**
     * A stable, unguessable token for unsubscribe links.
     */
    public function emailToken(): string
    {
        if (! $this->email_token) {
            $this->forceFill(['email_token' => Str::random(40)])->saveQuietly();
        }

        return $this->email_token;
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest()->latest('id');
    }

    public function owner(): ?\Statamic\Contracts\Auth\User
    {
        return $this->owner_id ? User::find($this->owner_id) : null;
    }

    public function name(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->email ?: __('Contact #:id', ['id' => $this->id]));
    }

    public function activityLabel(): string
    {
        return __('Contact');
    }

    public function avatarUrl(int $size = 80): ?string
    {
        return $this->email ? 'https://www.gravatar.com/avatar/'.md5(mb_strtolower(trim($this->email)))."?s={$size}&d=404" : null;
    }

    /**
     * Find a contact by their main email or any alias email ("AKA mode").
     */
    public static function findByEmail(string $email): ?self
    {
        $email = mb_strtolower(trim($email));

        return static::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('email', $email))
            ->first();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('email', 'like', $like)));
    }
}
