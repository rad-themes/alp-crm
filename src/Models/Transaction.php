<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RadThemes\AlpCrm\Database\Factories\TransactionFactory;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Concerns\HasBlueprint;
use RadThemes\AlpCrm\Models\Concerns\HasTags;
use RadThemes\AlpCrm\Support\Money;
use RadThemes\AlpCrm\Support\Settings;

/**
 * A sale or refund — entered by hand, synced from a payment provider, or a payment against an invoice.
 *
 * @property int $id
 * @property string $type sale|refund
 * @property string $status succeeded|pending|failed|refunded|cancelled
 * @property float $amount
 * @property string $currency
 */
class Transaction extends Model
{
    use HasBlueprint, HasFactory, HasTags;

    public const STATUSES = ['succeeded', 'pending', 'failed', 'refunded', 'cancelled'];

    protected $table = 'crm_transactions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'fee' => 'float',
            'date' => 'date',
            'data' => 'array',
        ];
    }

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $transaction->currency ??= Settings::currency();
            $transaction->date ??= today();
        });

        static::saving(function (Transaction $transaction) {
            $transaction->fee ??= 0;
        });

        static::created(function (Transaction $transaction) {
            CrmEvent::fire('transaction.created', $transaction);

            if ($transaction->contact && $transaction->status === 'succeeded') {
                $transaction->contact->logActivity('transaction', __(':type of :amount', [
                    'type' => $transaction->type === 'refund' ? __('Refund') : __('Payment'),
                    'amount' => $transaction->money(),
                ]), ['transaction_id' => $transaction->id]);
            }
        });

        static::saved(function (Transaction $transaction) {
            $transaction->invoice?->refreshPaymentStatus();
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->invoice?->refreshPaymentStatus();
        });
    }

    public static function blueprintHandle(): string
    {
        return 'transaction';
    }

    protected function blueprintColumns(): array
    {
        return ['title', 'reference', 'type', 'status', 'amount', 'fee', 'date'];
    }

    protected function relationBlueprintHandles(): array
    {
        return ['contact', 'currency', 'tags'];
    }

    protected function relationBlueprintValues(): array
    {
        return [
            'contact' => $this->contact_id ? [$this->contact_id] : [],
            'currency' => $this->currency,
            'tags' => $this->tags->pluck('name')->all(),
            'date' => $this->date?->format('Y-m-d'),
        ];
    }

    protected function fillRelationsFromBlueprint(array $values): void
    {
        if (array_key_exists('contact', $values)) {
            $this->contact_id = collect($values['contact'])->first();
            $this->company_id = $this->contact_id ? Contact::find($this->contact_id)?->company_id : null;
        }

        if (array_key_exists('currency', $values)) {
            $this->currency = strtoupper((string) (collect($values['currency'])->first() ?: Settings::currency()));
        }

        if (array_key_exists('tags', $values)) {
            $this->setTagsLater((array) $values['tags']);
        }
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function money(): string
    {
        return Money::format($this->type === 'refund' ? -$this->amount : $this->amount, $this->currency);
    }

    public function name(): string
    {
        return $this->title ?: ($this->reference ?: __('Transaction #:id', ['id' => $this->id]));
    }

    /**
     * Revenue: succeeded sales minus succeeded refunds.
     */
    public static function revenue(Builder $query): float
    {
        $totals = (clone $query)->where('status', 'succeeded')
            ->selectRaw("sum(case when type = 'refund' then -amount else amount end) as revenue")
            ->value('revenue');

        return Money::round((float) $totals);
    }
}
