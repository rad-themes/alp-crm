<?php

namespace RadThemes\RadpackCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\RadpackCrm\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => 'customer',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
        ];
    }
}
