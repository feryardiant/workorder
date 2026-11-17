<?php

declare(strict_types=1);

use App\Actions\Projects\CancelTask;
use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Livewire\Attachments\Index as AttachmentIndex;
use App\Livewire\ProjectKinds\Form;
use App\Livewire\ProjectKinds\Form as ProjectKindForm;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\View;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('creates a project from a kind with a daily code and copied task tree', function (): void {
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

it('cancels the tasks below a canceled task and the project itself', function (): void {
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Beta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Second']);

    $customer = contactOrganization($this->tenant);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    $first = $project->tasks->first();
    $second = $project->tasks->last();

    app(CancelTask::class)->handle($first, 'Customer withdrew');

    expect($first->refresh()->canceled_at)->not->toBeNull()
        ->and($project->refresh()->canceled_date)->not->toBeNull()
        ->and($project->remark)->toBe('Customer withdrew')
        ->and($second->refresh()->isDisabled())->toBeTrue();

    expect(fn () => app(CancelTask::class)->handle($second, '   '))
        ->toThrow(ValidationException::class);
});

it('saves a project kind task tree through the form', function (): void {
    Livewire::test(ProjectKindForm::class)
        ->set('title', 'Gamma')
        ->set('summary', 'Reusable work')
        ->set('tasks', [
            ['title' => 'One', 'summary' => '', 'subtasks' => [['title' => 'One.a', 'summary' => '']]],
            ['title' => 'Two', 'summary' => 'second', 'subtasks' => []],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $kind = ProjectKind::query()->where('title', 'Gamma')->sole();

    expect($kind->tasks)->toHaveCount(2)
        ->and($kind->tasks->first()->children)->toHaveCount(1)
        ->and($kind->tasks->first()->position)->toBe(1);
});

it('lists only tenant attachments and updates their title', function (): void {
    $project = Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($this->tenant)->getKey(),
    ]);

    $attachment = Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'name' => 'report.pdf',
        'mime' => 'application/pdf',
    ]);

    $other = Project::factory()->create();
    Attachment::factory()->create([
        'attachable_type' => $other->getMorphClass(),
        'attachable_id' => $other->getKey(),
        'name' => 'stranger.png',
        'mime' => 'image/png',
    ]);

    Livewire::test(AttachmentIndex::class)
        ->assertSee('report.pdf')
        ->assertDontSee('stranger.png')
        ->set('type', 'image')
        ->assertDontSee('report.pdf')
        ->set('type', 'pdf')
        ->assertSee('report.pdf')
        ->call('openEdit', $attachment->getKey())
        ->set('editTitle', 'Quarterly report')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($attachment->refresh()->title)->toBe('Quarterly report');
});

it('marks a task as a sub-task only when it has a parent', function (): void {
    $task = Task::factory()->create();

    expect($task->isSubTask())->toBeFalse();
});

it('requires a reason before canceling and finishes with one task left', function (): void {
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

it('creates a project uuid even when model events are disabled', function (): void {
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Zeta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);

    $project = Model::withoutEvents(fn (): Project => app(CreateProject::class)->handle(
        $this->tenant,
        $kind,
        contactOrganization($this->tenant),
    ));

    expect($project->uuid)->not->toBeEmpty();
});

it('keeps project kinds and slug lookups inside the tenant', function (): void {
    $other = Organization::factory()->asTenantOf($this->tenant)->create();

    $mine = ProjectKind::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'title' => 'Mine',
    ]);
    $theirs = ProjectKind::factory()->create([
        'owner_organization_id' => $other->getKey(),
        'title' => 'Theirs',
    ]);

    Livewire::test(App\Livewire\ProjectKinds\Index::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');

    $kindTask = ProjectKindTask::factory()->for($mine, 'kind')->create(['position' => 1]);
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
        ->and($foreign->fresh()->slug)->toBe($project->slug)
        ->and($kindTask->refresh()->title)->not->toBeEmpty();
});

it('creates a project from the index modal', function (): void {
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Eta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    $customer = contactOrganization($this->tenant);
    $member = auth()->user()?->person;

    Livewire::test(Index::class)
        ->set('createKindId', $kind->getKey())
        ->set('createCustomerId', $customer->getKey())
        ->set('createAssigneeId', $member?->getKey())
        ->set('createSummary', 'Kickoff')
        ->call('create')
        ->assertHasNoErrors();

    expect(Project::query()->where('owner_organization_id', $this->tenant->getKey())->sole()->tasks)->toHaveCount(1);
});

it('renders the project screens', function (): void {
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Delta']);
    $first = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Kickoff']);
    ProjectKindTask::factory()->subTaskOf($first)->create(['position' => 1, 'title' => 'Prepare agenda']);

    $customer = contactOrganization($this->tenant);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    Livewire::test(Index::class)->assertOk()->assertSee('Delta');
    Livewire::test(View::class, ['project' => $project])
        ->assertOk()
        ->assertSee('Kickoff')
        ->assertSee('Prepare agenda');
    Livewire::test(App\Livewire\ProjectKinds\Index::class)->assertOk()->assertSee('Delta');
    Livewire::test(Form::class)->assertOk();
    Livewire::test(Form::class, ['kind' => $kind->getKey()])
        ->assertOk()
        ->assertSee('Kickoff');
});
