<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Livewire\Projects\View;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('requires a reason before canceling and finishes with one task left', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Epsilon']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Only']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Last']);

    $customer = contactOrganization($this->tenant);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    $first = $project->tasks->first();
    $second = $project->tasks->last();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openCancel', $first->getKey())
        ->set('cancelReason', '')
        ->call('cancelTask')
        ->assertHasErrors('reason');

    expect($first->refresh()->canceled_at)->toBeNull();

    $second->update(['finished_at' => now()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('finishProject');

    expect($project->refresh()->finished_date)->not->toBeNull();
});
it('hides task actions on a canceled card', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Iota']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Doomed']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    Livewire::test(View::class, ['project' => $project])
        ->assertSee("dispatch('assign-member'", false);

    $project->update(['canceled_date' => today(), 'remark' => 'Called off']);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee(__('Disabled'))
        ->assertDontSee("dispatch('assign-member'", false)
        ->assertDontSee("dispatch('assign-contact'", false);
});
it('finishes a started task', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Mu']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $task->update(['started_at' => now()->subHour()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('finishTask', $task->getKey())
        ->assertHasNoErrors();

    expect($task->refresh()->finished_at)->not->toBeNull();
});
it('starts the project when a task starts on a not started or scheduled project', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Starter']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $project->update(['started_date' => now()->addWeek()->toDateString()]);

    $task = $project->tasks->first();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $task->getKey())
        ->assertSee(__('Finish task'));

    expect($project->refresh()->started_date?->isToday())->toBeTrue()
        ->and($task->refresh()->started_at)->not->toBeNull()
        ->and($task->status->isOnGoing())->toBeTrue();
});
it('leaves an ongoing project untouched when a task starts', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Steady']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $project->update(['started_date' => now()->subMonth()->toDateString()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $project->tasks->first()->getKey());

    expect($project->refresh()->started_date?->toDateString())->toBe(now()->subMonth()->toDateString());
});
it('starts the parent task when a sub-task is finished', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Parenting']);
    $parent = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    ProjectKindTask::factory()->subTaskOf($parent)->create(['position' => 1]);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $subTask = $task->subTasks->first();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('toggleSubTask', $subTask->getKey());

    expect($task->refresh()->started_at)->not->toBeNull()
        ->and($subTask->refresh()->finished_at)->not->toBeNull();
});
it('hides actions and staticises assignments on a finished task', function (): void {
    /** @var TestCase $this */
    $assignee = auth()->user()?->person;

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Done']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant), $assignee);
    $task = $project->tasks->first();
    $task->update(['started_at' => now()->subHour(), 'finished_at' => now()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee($assignee?->name)
        ->assertDontSee("dispatch('assign-member'", false)
        ->assertDontSee("dispatch('assign-contact'", false)
        ->assertDontSee(__('Start task'))
        ->assertDontSee(__('Finish task'));
});
it('locks sub-task checkboxes once the parent task is finished', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Locked']);
    $parent = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    ProjectKindTask::factory()->subTaskOf($parent)->create(['position' => 1]);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $subTask = $task->subTasks->first();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('toggleSubTask', $subTask->getKey());

    expect($subTask->refresh()->finished_at)->not->toBeNull();

    $task->update(['started_at' => now()->subHour(), 'finished_at' => now()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('toggleSubTask', $subTask->getKey())
        ->assertForbidden();
});

it('shows attachment counts and the aggregated list on the project view', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Files']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();

    Attachment::factory()->create(['attachable_type' => $project->getMorphClass(), 'attachable_id' => $project->getKey(), 'name' => 'brief.pdf']);
    Attachment::factory()->create(['attachable_type' => $task->getMorphClass(), 'attachable_id' => $task->getKey(), 'name' => 'photo.jpg']);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee('1 '.__('attachment'))
        ->assertSee('brief.pdf')
        ->assertSee('photo.jpg');
});

it('renders the project view with its task tree', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Delta']);
    $first = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Kickoff']);
    ProjectKindTask::factory()->subTaskOf($first)->create(['position' => 1, 'title' => 'Prepare agenda']);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    Livewire::test(View::class, ['project' => $project])
        ->assertOk()
        ->assertSee('Kickoff')
        ->assertSee('Prepare agenda');
});
