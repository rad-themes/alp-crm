<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RadThemes\RadpackCrm\Database\Factories\QuoteFactory;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Models\Concerns\HasLineItems;
use RadThemes\RadpackCrm\Models\Concerns\LogsActivity;
use RadThemes\RadpackCrm\Support\Documents;
use RadThemes\RadpackCrm\Support\Numbering;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * @property int $id
 * @property string $number
 * @property string $status draft|sent|accepted|declined
 * @property string $currency
 * @property float $total
 * @property string $token
 */
class Quote extends Model
{
    use HasFactory, HasLineItems, LogsActivity;

    public const STATUSES = ['draft', 'sent', 'accepted', 'declined'];

    protected $table = 'crm_quotes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'subtotal' => 'float',
            'discount' => 'float',
            'tax_total' => 'float',
            'total' => 'float',
            'data' => 'array',
        ];
    }

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            $quote->number ??= Numbering::next(self::class, (string) Settings::get('quote_prefix'));
            $quote->issue_date ??= today();
            $quote->valid_until ??= today()->addDays((int) Settings::get('quote_valid_days'));
            $quote->terms ??= Settings::get('quote_terms');
        });
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function name(): string
    {
        return $this->number;
    }

    public function statusLabel(?string $status): string
    {
        return Documents::statusLabels()[$status] ?? ucfirst((string) $status);
    }

    public function activityLabel(): string
    {
        return __('Quote');
    }

    public function isExpired(): bool
    {
        return $this->status === 'sent' && $this->valid_until?->isPast() && ! $this->valid_until->isToday();
    }

    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function canBeRespondedTo(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true) && ! $this->isExpired();
    }

    public function respond(bool $accepted, ?string $by = null): void
    {
        $this->update(['status' => $accepted ? 'accepted' : 'declined', 'responded_at' => now(), 'responded_by' => $by]);

        $this->logActivity($accepted ? 'accepted' : 'declined', $accepted ? __('Quote accepted') : __('Quote declined'), ['by' => $by]);
        CrmEvent::fire($accepted ? 'quote.accepted' : 'quote.declined', $this, ['by' => $by]);
        $this->contact?->logActivity('quote_'.($accepted ? 'accepted' : 'declined'), __(':number :result', [
            'number' => $this->number,
            'result' => $accepted ? __('accepted') : __('declined'),
        ]));
    }

    public function convertToInvoice(): Invoice
    {
        return $this->invoice ?? tap(Invoice::create([
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'quote_id' => $this->id,
            'title' => $this->title,
            'currency' => $this->currency,
            'notes' => $this->notes,
        ]), function (Invoice $invoice) {
            $invoice->syncItems($this->items->map->toEditorArray()->all(), (float) $this->discount);
        });
    }
}
