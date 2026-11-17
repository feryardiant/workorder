<?php

declare(strict_types=1);

use App\Actions\Projects\CancelTask;
use App\Actions\Projects\CreateProject;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Attachments\Index as AttachmentIndex;
use App\Livewire\Attachments\Partials\Edit as AttachmentEdit;
use App\Livewire\Attachments\Partials\Upload;
use App\Livewire\ProjectKinds\Form;
use App\Livewire\ProjectKinds\Form as ProjectKindForm;
use App\Livewire\ProjectKinds\Partials\SubtaskForm;
use App\Livewire\ProjectKinds\Partials\TaskForm;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Partials\AssignContact;
use App\Livewire\Projects\Partials\AssignMember;
use App\Livewire\Projects\View;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->assertSee('report.pdf');

    Livewire::test(AttachmentEdit::class)
        ->call('open', $attachment->getKey())
        ->set('title', 'Quarterly report')
        ->call('save')
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

it('filters projects by a started date range', function (): void {
    $january = Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => ProjectKind::factory(['owner_organization_id' => $this->tenant->getKey()]),
        'customer_id' => contactOrganization($this->tenant)->getKey(),
        'started_date' => '2026-01-10',
        'title' => 'January project',
    ]);

    Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => ProjectKind::factory(['owner_organization_id' => $this->tenant->getKey()]),
        'customer_id' => contactOrganization($this->tenant)->getKey(),
        'started_date' => '2026-06-10',
        'title' => 'June project',
    ]);

    Livewire::test(Index::class)
        ->set('startedRange', ['start' => '2026-01-01', 'end' => '2026-01-31'])
        ->assertSee($january->title)
        ->assertDontSee('June project');
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

it('opens member and contact assignment for the owner', function (): void {
    $owner = actingAsMember($this->tenant, Role::Owner);
    $member = $owner->person;

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Theta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Active']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();

    $contact = contactOrganization($this->tenant, type: OrganizationRelationshipType::Vendor);

    Livewire::test(AssignMember::class)
        ->call('open', $task->getKey())
        ->assertSet('showModal', true)
        ->set('memberId', $member?->getKey())
        ->call('assign');

    Livewire::test(AssignContact::class)
        ->call('open', $task->getKey())
        ->assertSet('showModal', true)
        ->set('contactId', $contact->getKey())
        ->call('assign');

    expect($task->refresh()->assignee_id)->toBe($member?->getKey())
        ->and($task->contact_id)->toBe($contact->getKey());
});

it('hides task actions on a canceled card', function (): void {
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

it('excludes the current assignee from the member modal', function (): void {
    $me = auth()->user();
    $teammate = User::factory()->asMemberOf($this->tenant)->create();

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Kappa']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $task->update(['assignee_id' => $me?->person?->getKey()]);

    Livewire::test(AssignMember::class)
        ->call('open', $task->getKey())
        ->assertSee($teammate->name)
        ->assertDontSee($me?->name);

});

it('seeds every copied task with the project assignee', function (): void {
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

it('finishes a started task', function (): void {
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

it('stores contacts on kind tasks and copies them to project tasks', function (): void {
    $contact = contactOrganization($this->tenant, type: OrganizationRelationshipType::Vendor);

    Livewire::test(ProjectKindForm::class)
        ->set('title', 'Contacted')
        ->set('tasks', [[
            'title' => 'With contact',
            'summary' => '',
            'contact_id' => $contact->getKey(),
            'subtasks' => [[
                'title' => 'Sub with contact',
                'summary' => '',
                'contact_id' => $contact->getKey(),
            ]],
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $kind = ProjectKind::query()->where('title', 'Contacted')->sole();
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    expect($kind->tasks->first()->contact_id)->toBe($contact->getKey())
        ->and($kind->tasks->first()->children->first()->contact_id)->toBe($contact->getKey())
        ->and($project->tasks->first()->contact_id)->toBe($contact->getKey())
        ->and($project->tasks->first()->subTasks->first()->contact_id)->toBe($contact->getKey());
});

it('edits kind task drafts through the partial components', function (): void {
    Livewire::test(TaskForm::class)
        ->call('open', null, [])
        ->set('title', 'Draft task')
        ->set('contactId', null)
        ->call('save')
        ->assertDispatched('kind-task-saved');

    Livewire::test(SubtaskForm::class)
        ->call('open', null, [])
        ->set('title', 'Draft sub-task')
        ->call('save')
        ->assertDispatched('kind-subtask-saved');
});

it('shows contacts on the project kind task table', function (): void {
    $contact = contactOrganization($this->tenant, type: OrganizationRelationshipType::Vendor);

    $kind = ProjectKind::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'title' => 'Contact column',
    ]);

    ProjectKindTask::factory()->for($kind, 'kind')->create([
        'position' => 1,
        'title' => 'Contacted',
        'contact_id' => $contact->getKey(),
    ]);

    Livewire::test(ProjectKindForm::class, ['kind' => $kind->getKey()])
        ->assertSee($contact->name);
});

it('uploads attachments for projects and tasks but not sub-tasks', function (): void {
    Storage::fake();

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Uploads']);
    $first = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    ProjectKindTask::factory()->subTaskOf($first)->create(['position' => 1]);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $subTask = $task->subTasks->first();

    Livewire::test(Upload::class)
        ->call('open', 'project', $project->getKey())
        ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Upload::class)
        ->call('open', 'task', $task->getKey())
        ->set('uploads', [UploadedFile::fake()->image('photo.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect($project->attachments()->count())->toBe(1)
        ->and($task->attachments()->count())->toBe(1);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee('1 '.__('attachment'))
        ->assertSee('brief.pdf')
        ->assertSee('photo.jpg');

    $attachment = $task->attachments()->sole();

    Livewire::test(Upload::class)
        ->call('open', 'task', $task->getKey())
        ->call('remove', $attachment->getKey());

    expect($task->attachments()->count())->toBe(0);

    Livewire::test(Upload::class)
        ->call('open', 'task', $subTask->getKey())
        ->assertForbidden();
});
