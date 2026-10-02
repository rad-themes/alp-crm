<?php

namespace RadThemes\RadpackCrm\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\LineItem;
use RadThemes\RadpackCrm\Support\Documents;
use RadThemes\RadpackCrm\Support\Money;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * Shared behaviour for quotes and invoices: line items, totals, client and public token.
 */
trait HasLineItems
{
    /**
     * "<type>.created" fires once the first line items are saved, so the payload has totals.
     */
    protected bool $createdEventFired = false;

    public static function bootHasLineItems(): void
    {
        static::creating(function ($document) {
            $document->token ??= Str::random(48);
            $document->currency ??= Settings::currency();
            $document->status ??= 'draft';
        });

        static::deleting(function ($document) {
            $document->items()->delete();
        });

        static::updated(function ($document) {
            if ($document->wasChanged('sent_at') && $document->sent_at && ! $document->getOriginal('sent_at')) {
                CrmEvent::fire(Documents::type($document).'.sent', $document);
            }
        });
    }

    public function items(): MorphMany
    {
        return $this->morphMany(LineItem::class, 'document')->orderBy('position');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Replace the line items and recalculate the totals.
     *
     * @param  array<int, array{description?: string, quantity?: float|string, unit_price?: float|string, tax_name?: ?string, tax_rate?: float|string}>  $items
     */
    public function syncItems(array $items, float $discount = 0): void
    {
        $this->items()->delete();

        $lines = collect($items)
            ->filter(fn ($item) => trim((string) ($item['description'] ?? '')) !== '')
            ->values()
            ->map(fn ($item, $position) => [
                'position' => $position,
                'description' => trim((string) $item['description']),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => Money::round((float) ($item['unit_price'] ?? 0)),
                'tax_name' => ($item['tax_name'] ?? null) ?: null,
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
            ]);

        $totals = static::calculate($lines->all(), $discount, (bool) Settings::get('prices_include_tax'));

        $this->items()->createMany($lines->map(fn ($line, $i) => $line + ['total' => $totals['lines'][$i]])->all());
        $this->forceFill(collect($totals)->except('lines')->all())->save();
        $this->unsetRelation('items');

        if ($this->wasRecentlyCreated && ! $this->createdEventFired) {
            $this->createdEventFired = true;
            CrmEvent::fire(Documents::type($this).'.created', $this);
        }
        $this->unsetRelation('items');
    }

    /**
     * @param  array<int, array{quantity: float, unit_price: float, tax_rate: float}>  $lines
     * @return array{subtotal: float, discount: float, tax_total: float, total: float, lines: array<int, float>}
     */
    public static function calculate(array $lines, float $discount = 0, bool $pricesIncludeTax = false): array
    {
        $net = [];
        $tax = [];

        foreach ($lines as $i => $line) {
            $gross = $line['quantity'] * $line['unit_price'];
            $rate = max(0, $line['tax_rate']) / 100;

            $net[$i] = $pricesIncludeTax ? $gross / (1 + $rate) : $gross;
            $tax[$i] = $net[$i] * $rate;
        }

        $subtotal = array_sum($net);
        $discount = min(max(0, $discount), $subtotal);
        $ratio = $subtotal > 0 ? ($subtotal - $discount) / $subtotal : 0;
        $taxTotal = array_sum($tax) * $ratio;

        return [
            'subtotal' => Money::round($subtotal),
            'discount' => Money::round($discount),
            'tax_total' => Money::round($taxTotal),
            'total' => Money::round($subtotal - $discount + $taxTotal),
            'lines' => array_map(fn ($amount) => Money::round($amount), $net),
        ];
    }

    public function money(float|string|null $amount): string
    {
        return Money::format($amount, $this->currency);
    }

    public function clientName(): ?string
    {
        return $this->contact?->name() ?? $this->company?->name;
    }

    public function clientEmail(): ?string
    {
        return $this->contact?->email ?? $this->company?->email;
    }
}
