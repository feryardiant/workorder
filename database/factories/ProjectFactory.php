<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = \sprintf('%s - %s-%s', 'Kind', now()->format('Ymd'), Str::upper(Str::random(6)));

        return [
            'uuid' => (string) Str::uuid7(),
            'owner_organization_id' => Organization::factory(),
            'kind_id' => ProjectKind::factory(),
            'customer_type' => (new Organization)->getMorphClass(),
            'customer_id' => Organization::factory(),
            'assignee_id' => null,
            'code' => $code,
            'slug' => Str::slug($code),
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'settings' => null,
            'mandated_date' => null,
            'started_date' => null,
            'finished_date' => null,
            'canceled_date' => null,
        ];
    }
}
