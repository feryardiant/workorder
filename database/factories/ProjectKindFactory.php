<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ProjectKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectKind>
 */
class ProjectKindFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_organization_id' => Organization::factory(),
            'title' => ucfirst(fake()->unique()->sentence(3)),
            'summary' => fake()->sentence(),
        ];
    }
}
