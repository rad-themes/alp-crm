<?php

namespace RadThemes\AlpCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'title' => fake()->sentence(3),
            'status' => 'draft',
            'currency' => 'USD',
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function withItems(array $items = [['description' => 'Design', 'quantity' => 2, 'unit_price' => 500]], float $discount = 0): static
    {
        return $this->afterCreating(fn (Invoice $invoice) => $invoice->syncItems($items, $discount));
    }
}
