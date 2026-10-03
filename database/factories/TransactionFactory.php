<?php

namespace RadThemes\AlpCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\AlpCrm\Models\Transaction;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'type' => 'sale',
            'status' => 'succeeded',
            'amount' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'USD',
            'date' => today(),
        ];
    }
}
