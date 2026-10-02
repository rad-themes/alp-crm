<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RadThemes\RadpackCrm\Database\Factories\InvoiceFactory;
use RadThemes\RadpackCrm\Models\Concerns\HasLineItems;
use RadThemes\RadpackCrm\Models\Concerns\LogsActivity;
use RadThemes\RadpackCrm\Support\Documents;
use RadThemes\RadpackCrm\Support\Money;
use RadThemes\RadpackCrm\Support\Numbering;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * @property int $id
 * @property string $number
 * @property string $status draft|sent|partial|paid|void
 * @property string $currency
 * @property float $total
 * @property float $amount_paid
 * @property string $token
 */
class Invoice extends Model
{
    use HasFactory, HasLineItems, LogsActivity;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'void'];

    protected $table = 'crm_invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'float',
            'discount' => 'float',
            'tax_total' => 'float',
            'total' => 'float',
            'amount_paid' => 'float',
            'data' => 'array',
        ];
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->number ??= Numbering::next(self::class, (string) Settings::get('invoice_prefix'));
            $invoice->issue_date ??= today();
            $invoice->due_date ??= today()->addDays((int) Settings::get('payment_terms_days'));
            $invoice->terms ??= Settings::get('invoice_terms');
        });
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest('date')->latest('id');
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
        return __('Invoice');
    }

    public function balance(): float
    {
        return Money::round(max(0, $this->total - $this->amount_paid));
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partial'], true) && $this->due_date?->isPast() && ! $this->due_date->isToday();
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['sent', 'partial'], true) && $this->balance() > 0;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'partial']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', today());
    }

    /**
     * Record a payment against the invoice as a transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordPayment(float $amount, array $attributes = []): Transaction
    {
        $payment = $this->payments()->create(array_merge([
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'title' => __('Payment for :number', ['number' => $this->number]),
            'type' => 'sale',
            'status' => 'succeeded',
            'amount' => Money::round($amount),
            'currency' => $this->currency,
            'date' => today(),
        ], $attributes));

        // Saving the payment recalculates the invoice (see Transaction::booted); reload to pick that up.
        $this->refresh();

        return $payment;
    }

    /**
     * Recalculate amount paid and status from successful payments (refunds count negative).
     */
    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $paid = $this->payments()->where('status', 'succeeded')->get()
            ->sum(fn (Transaction $payment) => $payment->type === 'refund' ? -$payment->amount : $payment->amount);

        $wasPaid = $this->status === 'paid';
        $status = match (true) {
            $paid >= $this->total && $this->total > 0 => 'paid',
            $paid > 0 => 'partial',
            $this->status === 'draft' => 'draft',
            default => 'sent',
        };

        $this->update([
            'amount_paid' => Money::round($paid),
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($this->paid_at ?? now()) : null,
        ]);

        if ($status === 'paid' && ! $wasPaid) {
            $this->contact?->logActivity('invoice_paid', __(':number paid', ['number' => $this->number]));
        }
    }
}
