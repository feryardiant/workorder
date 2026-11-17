<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectKindTask>
 */
class ProjectKindTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_kind_id' => ProjectKind::factory(),
            'parent_id' => null,
            'position' => 1,
            'title' => ucfirst(fake()->sentence(4)),
            'summary' => fake()->sentence(),
        ];
    }

    /**
     * Create the row as a sub-task of the given task.
     */
    public function subTaskOf(ProjectKindTask $parent): static
    {
        return $this->state(fn (): array => [
            'project_kind_id' => $parent->project_kind_id,
            'parent_id' => $parent->getKey(),
        ]);
    }
}
