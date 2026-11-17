<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
});

it('summarises projects and my open tasks', function (): void {
    /** @var TestCase $this */
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $organization->getKey(), 'title' => 'Kinds']);

    $ongoing = Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'kind_id' => $kind->getKey(),
        'started_date' => '2026-11-17',
        'mandated_date' => '2026-11-19',
        'title' => 'Ongoing project',
    ]);

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'kind_id' => $kind->getKey(),
        'mandated_date' => '2026-11-16',
        'title' => 'Overdue project',
    ]);

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'kind_id' => $kind->getKey(),
        'mandated_date' => '2026-11-20',
        'title' => 'Due project',
    ]);

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'kind_id' => $kind->getKey(),
        'started_date' => '2026-11-01',
        'finished_date' => '2026-11-05',
        'title' => 'Finished project',
    ]);

    $task = Task::factory()->create([
        'project_id' => $ongoing->getKey(),
        'assignee_id' => $owner->person?->getKey(),
        'title' => 'My open task',
    ]);

    Task::factory()->create([
        'project_id' => $ongoing->getKey(),
        'assignee_id' => $owner->person?->getKey(),
        'finished_at' => now(),
    ]);

    Task::factory()->create([
        'project_id' => $ongoing->getKey(),
        'assignee_id' => User::factory()->asMemberOf($organization)->create()->person?->getKey(),
    ]);

    $insideCustomer = Person::factory()->withoutCredential()->create();
    $organization->personCounterparties(OrganizationRelationshipType::Customer)->attach($insideCustomer, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => '2026-11-18',
    ]);

    $outsideCustomer = Person::factory()->withoutCredential()->create();
    $organization->personCounterparties(OrganizationRelationshipType::Customer)->attach($outsideCustomer, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => '2026-10-01',
    ]);

    $vendor = Person::factory()->withoutCredential()->create();
    $organization->personCounterparties(OrganizationRelationshipType::Vendor)->attach($vendor, [
        'type' => OrganizationRelationshipType::Vendor,
        'start_date' => '2026-11-18',
    ]);

    $component = Livewire::test(Dashboard::class);
    expect($component->instance()->newCustomers)->toBe(1)
        ->and($component->instance()->onGoingProjects)->toBe(1)
        ->and($component->instance()->overdueProjects)->toBe(1)
        ->and($component->instance()->dueThisWeek)->toBe(2)
        ->and($component->instance()->myTasks)->toHaveCount(1);

    $component
        ->assertSee('Overdue project')
        ->assertSee($task->title)
        ->assertSee(__('Projects needing attention'))
        ->assertSee(__('My tasks'));
});
