<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Projects\CreateProject;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Local-only sample projects built from seeded organizations.
 */
class ProjectSeeder extends Seeder
{
    public function __construct(private readonly CreateProject $createProject)
    {
        // .
    }

    public function run(?Organization $root = null): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $root ??= Organization::query()->rootTenant()->first();

        if ($root === null) {
            return;
        }

        $customers = $this->customers($root);
        $assignees = $this->assignees($root);

        /** @var Collection<int, ProjectKind> $kinds */
        $kinds = ProjectKind::factory()
            ->count(5)
            ->create(['owner_organization_id' => $root->getKey()]);

        foreach ($kinds as $kind) {
            $this->createKindTasks($kind);
        }

        foreach ($kinds as $kind) {
            $count = fake()->numberBetween(5, 15);

            for ($i = 0; $i < $count; $i++) {
                $project = $this->createProject->handle(
                    $root,
                    $kind,
                    $customers->random(),
                    $assignees->isEmpty() ? null : $assignees->random(),
                    [
                        'summary' => fake()->paragraph(),
                        'mandated_date' => fake()->boolean(50) ? now()->addDays(fake()->numberBetween(7, 90))->toDateString() : null,
                        'started_date' => fake()->boolean(50) ? now()->subDays(fake()->numberBetween(1, 30))->toDateString() : null,
                    ],
                );

                if (fake()->boolean(20)) {
                    $project->update([
                        'started_date' => now()->subDays(60)->toDateString(),
                        'finished_date' => now()->subDays(5)->toDateString(),
                    ]);
                } elseif (fake()->boolean(15)) {
                    $project->update([
                        'started_date' => now()->subDays(45)->toDateString(),
                        'canceled_date' => now()->subDays(3)->toDateString(),
                        'remark' => fake()->sentence(),
                    ]);
                }
            }
        }
    }

    /**
     * @return Collection<int, Organization>
     */
    private function customers(Organization $root): Collection
    {
        $customers = $root->organizationCounterparties(OrganizationRelationshipType::Customer)->get();

        if ($customers->isNotEmpty()) {
            return $customers;
        }

        return Organization::factory()
            ->count(3)
            ->create(['owner_organization_id' => $root->getKey()])
            ->each(function (Organization $customer) use ($root): void {
                $root->organizationCounterparties(OrganizationRelationshipType::Customer)->attach($customer, [
                    'type' => OrganizationRelationshipType::Customer,
                    'start_date' => now(),
                ]);
            });
    }

    /**
     * @return Collection<int, Person>
     */
    private function assignees(Organization $root): Collection
    {
        return $root->members()->get();
    }

    /**
     * Build a kind's ordered task tree.
     */
    private function createKindTasks(ProjectKind $kind): void
    {
        $count = fake()->numberBetween(5, 8);

        for ($position = 1; $position <= $count; $position++) {
            $task = ProjectKindTask::factory()
                ->for($kind, 'kind')
                ->create(['position' => $position]);

            if (fake()->boolean(60)) {
                foreach (range(1, fake()->numberBetween(1, 2)) as $childPosition) {
                    ProjectKindTask::factory()
                        ->subTaskOf($task)
                        ->create(['position' => $childPosition]);
                }
            }
        }
    }
}
