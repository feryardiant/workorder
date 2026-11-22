<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'assignee_id' => null,
            'contact_id' => null,
            'slug' => Str::slug(fake()->unique()->sentence(4)),
            'code' => null,
            'title' => ucfirst(fake()->sentence(4)),
            'summary' => fake()->sentence(),
            'remark' => null,
            'order' => 1,
            'started_at' => null,
            'finished_at' => null,
            'canceled_at' => null,
            'postponed_at' => null,
        ];
    }
}
