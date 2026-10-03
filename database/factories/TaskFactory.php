<?php

namespace RadThemes\AlpCrm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RadThemes\AlpCrm\Models\Task;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'type' => 'task',
            'priority' => 'normal',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
        ];
    }

    public function done(): static
    {
        return $this->state(['completed_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(['starts_at' => now()->subDays(2)]);
    }
}
