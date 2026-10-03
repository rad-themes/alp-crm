<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property ?string $tax_name
 * @property float $tax_rate
 * @property float $total
 */
class LineItem extends Model
{
    public $timestamps = false;

    protected $table = 'crm_line_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'tax_rate' => 'float',
            'total' => 'float',
        ];
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'tax_name' => $this->tax_name,
            'tax_rate' => $this->tax_rate,
        ];
    }
}
