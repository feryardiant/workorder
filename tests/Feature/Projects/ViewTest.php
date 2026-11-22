<?php

declare(strict_types=1);

use App\Actions\Projects\CancelTask;
use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\View;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
it('postpones a task only after the previous one starts and records the reason', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Postpone']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Second']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $first = $project->tasks->first();
    $second = $project->tasks->last();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $second->getKey())
        ->assertForbidden();

    $first->update(['started_at' => now()->subHour()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $second->getKey())
        ->assertSet('showPostpone', true)
        ->set('postponeReason', '   ')
        ->call('postponeTask')
        ->assertHasErrors('postponeReason');

    expect($second->refresh()->postponed_at)->toBeNull();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $second->getKey())
        ->set('postponeReason', 'Waiting for the permit')
        ->call('postponeTask')
        ->assertHasNoErrors();

    $log = AuditLog::query()
        ->where('subject_type', $second->getMorphClass())
        ->where('subject_id', $second->getKey())
        ->latest('id')
        ->first();

    expect($second->refresh()->postponed_at)->not->toBeNull()
        ->and((string) $log?->changes)->toBe(__(':Task postponed: :reason', [
            'task' => $second->title,
            'reason' => 'Waiting for the permit',
        ]));
});
it('blocks the tasks after a postponed task until it starts', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Blocked']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Second']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 3, 'title' => 'Third']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $first = $project->tasks->get(0);
    $second = $project->tasks->get(1);
    $third = $project->tasks->get(2);

    $first->update(['started_at' => now()->subHour()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $second->getKey())
        ->set('postponeReason', 'Waiting for the permit')
        ->call('postponeTask');

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $third->getKey())
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $second->getKey());

    expect($second->refresh()->started_at)->not->toBeNull()
        ->and($second->postponed_at)->toBeNull();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $third->getKey())
        ->assertHasNoErrors();

    expect($third->refresh()->started_at)->not->toBeNull();
});
it('postpones a sub-task and blocks the sub-tasks after it', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Sub blocked']);
    $parent = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Parent']);
    ProjectKindTask::factory()->subTaskOf($parent)->create(['position' => 1, 'title' => 'First sub']);
    ProjectKindTask::factory()->subTaskOf($parent)->create(['position' => 2, 'title' => 'Second sub']);
    ProjectKindTask::factory()->subTaskOf($parent)->create(['position' => 3, 'title' => 'Third sub']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $firstSub = $task->subTasks->get(0);
    $secondSub = $task->subTasks->get(1);
    $thirdSub = $task->subTasks->get(2);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $secondSub->getKey())
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $firstSub->getKey());

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openPostpone', $secondSub->getKey())
        ->set('postponeReason', 'Waiting for the vendor')
        ->call('postponeTask')
        ->assertHasNoErrors();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $thirdSub->getKey())
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('toggleSubTask', $secondSub->getKey())
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $secondSub->getKey());

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $thirdSub->getKey());

    expect($secondSub->refresh()->started_at)->not->toBeNull()
        ->and($secondSub->postponed_at)->toBeNull()
        ->and($thirdSub->refresh()->started_at)->not->toBeNull();
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

it('lets only managers or the assignee edit and archive a project', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Perms']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    $member = actingAsMember($this->tenant, Role::Member);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openEdit')
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('archive')
        ->assertForbidden();

    $project->update(['assignee_id' => $member->person?->getKey()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openEdit')
        ->assertSet('showEdit', true);
});

it('updates the customer from the edit modal', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Customer edit']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $replacement = contactOrganization($this->tenant, ['name' => 'Replacement Customer']);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openEdit')
        ->set('customerId', $replacement->getKey())
        ->call('saveProject')
        ->assertHasNoErrors();

    expect($project->refresh()->customer_id)->toBe($replacement->getKey());
});

it('rejects state changes on finished and canceled records', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Locked states']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $task->update(['started_at' => now()->subHour(), 'finished_at' => now()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $task->getKey())
        ->assertForbidden();

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openTaskInfo', $task->getKey())
        ->assertForbidden();

    expect(fn () => app(CancelTask::class)->handle($task, 'reason'))
        ->toThrow(ValidationException::class);

    $project->update(['canceled_date' => today()]);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('openEdit')
        ->assertForbidden();
});

it('starts the project on the local date of the user', function (): void {
    /** @var TestCase $this */
    // Travel forward so the membership created in beforeEach starts before the frozen time.
    $frozen = CarbonImmutable::now()->addDay()->setTime(23, 30);
    $this->travelTo($frozen);

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Timezone']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    $user = auth()->user();
    $user->update(['timezone' => 'Pacific/Kiritimati']);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $project->tasks->first()->getKey());

    expect($project->refresh()->started_date?->toDateString())
        ->toBe($frozen->setTimezone('Pacific/Kiritimati')->toDateString());
});

it('reports the project as ongoing on the user local date', function (): void {
    /** @var TestCase $this */
    // Travel forward so the membership created in beforeEach starts before the frozen time.
    $this->travelTo(CarbonImmutable::now()->addDay()->setTime(23, 30));

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Status timezone']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    auth()->user()?->update(['timezone' => 'Pacific/Kiritimati']);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->call('startTask', $project->tasks->first()->getKey());

    expect($project->refresh()->status->isOnGoing())->toBeTrue();

    Livewire::test(Index::class)
        ->set('status', 'on_going')
        ->assertSee($project->title);
});
