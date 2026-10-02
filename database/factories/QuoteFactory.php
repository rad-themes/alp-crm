<?php

namespace RadThemes\RadpackCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Quote;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

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
        return $this->afterCreating(fn (Quote $quote) => $quote->syncItems($items, $discount));
    }
}
