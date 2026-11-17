<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('creates a project from a kind with a daily code and copied task tree', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Alpha']);
    $first = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    ProjectKindTask::factory()->subTaskOf($first)->create(['position' => 1, 'title' => 'First sub']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Second']);

    $customer = contactOrganization($this->tenant);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    expect($project->code)->toBe(\sprintf('Alpha - %s-001', now()->format('Ymd')))
        ->and($project->slug)->toBe('alpha-'.now()->format('Ymd').'-001')
        ->and($project->title)->toBe(\sprintf('Alpha for %s (%s)', $customer->name, now()->format('d-m-Y')))
        ->and($project->tasks)->toHaveCount(2)
        ->and($project->tasks->first()->subTasks)->toHaveCount(1)
        ->and($project->tasks->pluck('order')->all())->toBe([1, 2]);

    $second = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    expect($second->code)->toBe(\sprintf('Alpha - %s-002', now()->format('Ymd')));
});
it('creates a project uuid even when model events are disabled', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Zeta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);

    $project = Model::withoutEvents(fn (): Project => app(CreateProject::class)->handle(
        $this->tenant,
        $kind,
        contactOrganization($this->tenant),
    ));

    expect($project->uuid)->not->toBeEmpty();
});
it('seeds every copied task with the project assignee', function (): void {
    /** @var TestCase $this */
    $assignee = auth()->user()?->person;

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Lambda']);
    $task = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    ProjectKindTask::factory()->subTaskOf($task)->create(['position' => 1]);

    $project = app(CreateProject::class)->handle(
        $this->tenant,
        $kind,
        contactOrganization($this->tenant),
        $assignee,
    );

    $other = Person::factory()->create();
    $project->update(['assignee_id' => $other->getKey()]);

    expect($project->allTasks()->where('assignee_id', $assignee?->getKey())->count())->toBe(2)
        ->and($project->allTasks()->where('assignee_id', $other->getKey())->count())->toBe(0);
});
it('marks a task as a sub-task only when it has a parent', function (): void {
    $task = Task::factory()->create();

    expect($task->isSubTask())->toBeFalse();
});

it('resolves project slugs inside the current tenant', function (): void {
    /** @var TestCase $this */
    $other = Organization::factory()->asTenantOf($this->tenant)->create();

    $mine = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Slugger']);
    ProjectKindTask::factory()->for($mine, 'kind')->create(['position' => 1]);

    $theirs = ProjectKind::factory()->create(['owner_organization_id' => $other->getKey(), 'title' => 'Theirs']);
    ProjectKindTask::factory()->for($theirs, 'kind')->create(['position' => 1]);

    $project = app(CreateProject::class)->handle($this->tenant, $mine, contactOrganization($this->tenant));

    $foreign = Project::factory()->create([
        'owner_organization_id' => $other->getKey(),
        'slug' => $project->slug,
        'code' => $project->code,
        'kind_id' => $theirs->getKey(),
        'customer_id' => contactOrganization($other)->getKey(),
    ]);

    expect($project->resolveRouteBinding($project->slug)?->is($project))->toBeTrue()
        ->and($foreign->fresh()->slug)->toBe($project->slug);
});

it('creates task slugs that stay unique across tenants', function (): void {
    /** @var TestCase $this */
    $other = Organization::factory()->asTenantOf($this->tenant)->create();

    $make = function (Organization $tenant): Project {
        $kind = ProjectKind::factory()->create([
            'owner_organization_id' => $tenant->getKey(),
            'title' => 'Shared kind',
        ]);

        ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Same task']);

        return app(CreateProject::class)->handle($tenant, $kind, contactOrganization($tenant));
    };

    $first = $make($this->tenant);
    $second = $make($other);

    expect($first->tasks->first()->slug)->not->toBe($second->tasks->first()->slug);
});
