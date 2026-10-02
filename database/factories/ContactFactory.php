<?php

namespace RadThemes\RadpackCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\RadpackCrm\Models\Contact;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['lead', 'customer']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'data' => ['city' => fake()->city()],
        ];
    }

    public function customer(): static
    {
        return $this->state(['status' => 'customer']);
    }

    public function lead(): static
    {
        return $this->state(['status' => 'lead']);
    }
}
