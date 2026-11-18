<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Livewire\Projects\Index;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('creates a project from the index modal', function (): void {
    /** @var TestCase $this */
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
    /** @var TestCase $this */
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

it('renders the project index', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Delta']);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Delta')
        ->assertSee($project->title);
});

it('lists only the current tenant kinds in the create form', function (): void {
    /** @var TestCase $this */
    $other = Organization::factory()->asTenantOf($this->tenant)->create();

    ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Mine kind']);
    ProjectKind::factory()->create(['owner_organization_id' => $other->getKey(), 'title' => 'Foreign kind']);

    Livewire::test(Index::class)
        ->assertSee('Mine kind')
        ->assertDontSee('Foreign kind');
});

it('lets members create projects only for themselves', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Member create']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $customer = contactOrganization($this->tenant);

    $member = actingAsMember($this->tenant, Role::Member);
    $other = User::factory()->asMemberOf($this->tenant)->create();

    Livewire::test(Index::class)
        ->set('createKindId', $kind->getKey())
        ->set('createCustomerId', $customer->getKey())
        ->set('createAssigneeId', $other->person?->getKey())
        ->call('create')
        ->assertHasErrors('createAssigneeId');

    expect(Project::query()->count())->toBe(0);

    Livewire::test(Index::class)
        ->set('createKindId', $kind->getKey())
        ->set('createCustomerId', $customer->getKey())
        ->set('createAssigneeId', $member->person?->getKey())
        ->call('create')
        ->assertHasNoErrors();

    expect(Project::query()->sole()->assignee_id)->toBe($member->person?->getKey());
});

it('archives the selected projects', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    $first = app(CreateProject::class)->handle($this->tenant, $kind, $customer);
    $second = app(CreateProject::class)->handle($this->tenant, $kind, $customer);
    $kept = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    Livewire::test(Index::class)
        ->set('selected', [$first->getKey(), $second->getKey()])
        ->call('archiveSelected')
        ->assertSet('selected', [])
        ->assertSet('showBatchArchive', false);

    expect(Project::withTrashed()->whereKey([$first->getKey(), $second->getKey()])->whereNotNull('deleted_at')->count())->toBe(2)
        ->and(Project::query()->whereKey($kept->getKey())->exists())->toBeTrue();
});

it('archives only the selected projects a member may manage', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    $member = actingAsMember($this->tenant, Role::Member);
    $other = User::factory()->asMemberOf($this->tenant)->create();

    $own = app(CreateProject::class)->handle($this->tenant, $kind, $customer, $member->person);
    $foreign = app(CreateProject::class)->handle($this->tenant, $kind, $customer, $other->person);

    Livewire::test(Index::class)
        ->set('selected', [$own->getKey(), $foreign->getKey()])
        ->call('archiveSelected');

    expect(Project::withTrashed()->find($own->getKey())?->trashed())->toBeTrue()
        ->and(Project::withTrashed()->find($foreign->getKey())?->trashed())->toBeFalse();
});

it('filters archived projects', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    $archived = app(CreateProject::class)->handle($this->tenant, $kind, $customer, null, ['title' => 'Archived project']);
    $active = app(CreateProject::class)->handle($this->tenant, $kind, $customer, null, ['title' => 'Active project']);

    $archived->delete();

    Livewire::test(Index::class)
        ->set('status', ProjectStatus::Archived->value)
        ->assertSee($archived->title)
        ->assertDontSee($active->title);

    expect($archived->status)->toBe(ProjectStatus::Archived);
});

it('does not offer archive actions for archived projects', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    $archived = app(CreateProject::class)->handle($this->tenant, $kind, $customer, null, ['title' => 'Archived project']);
    $archived->delete();

    Livewire::test(Index::class)
        ->set('status', ProjectStatus::Archived->value)
        ->assertSee('Archived project')
        ->assertDontSee(__('Select all projects'))
        ->assertDontSeeHtml('confirmArchive(')
        ->assertDontSee(route('projects.view', ['project' => $archived->slug]));
});

it('tracks a partial page selection', function (): void {
    /** @var TestCase $this */
    $first = Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);

    Livewire::test(Index::class)
        ->set('selected', [$first->getKey()])
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', true);
});

it('clears the selection when the filters or page change', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $project = Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => $kind->getKey(),
    ]);

    Livewire::test(Index::class)
        ->set('selected', [$project->getKey()])
        ->set('kindId', (string) $kind->getKey())
        ->assertSet('selected', [])
        ->assertSet('someSelected', false)
        ->set('selected', [$project->getKey()])
        ->call('setPage', 2)
        ->assertSet('selected', []);
});
it('does nothing when archiving with no selection', function (): void {
    /** @var TestCase $this */
    Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);

    Livewire::test(Index::class)
        ->set('showBatchArchive', true)
        ->call('archiveSelected')
        ->assertSet('showBatchArchive', false);

    expect(Project::query()->count())->toBe(1);
});

it('clears a stale selection when the project is no longer selectable', function (): void {
    /** @var TestCase $this */
    $project = Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);

    $component = Livewire::test(Index::class)
        ->set('selected', [$project->getKey()])
        ->set('showBatchArchive', true);

    $project->delete();

    $component
        ->call('archiveSelected')
        ->assertSet('selected', [])
        ->assertSet('someSelected', false)
        ->assertSet('showBatchArchive', false);
});

it('only shows the batch actions when something is selected', function (): void {
    /** @var TestCase $this */
    $project = Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);

    Livewire::test(Index::class)
        ->assertDontSeeHtml('confirmArchiveSelected')
        ->set('selected', [$project->getKey()])
        ->assertSeeHtml('confirmArchiveSelected');
});

it('ignores project ids from another tenant', function (): void {
    /** @var TestCase $this */
    $other = Organization::factory()->asTenantOf($this->tenant)->create();
    $foreign = Project::factory()->create(['owner_organization_id' => $other->getKey()]);

    Livewire::test(Index::class)
        ->set('selected', [$foreign->getKey()])
        ->call('archiveSelected')
        ->assertSet('showBatchArchive', false);

    expect($foreign->fresh())->not->toBeNull();
});

it('rolls back the batch when one archive fails', function (): void {
    /** @var TestCase $this */
    $first = Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $second = Project::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);

    $event = 'eloquent.deleting: '.Project::class;

    Event::listen($event, function (Project $project) use ($second): void {
        if ($project->is($second)) {
            throw new RuntimeException('Archive failed.');
        }
    });

    try {
        Livewire::test(Index::class)
            ->set('selected', [$first->getKey(), $second->getKey()])
            ->call('archiveSelected')
            ->assertSet('showBatchArchive', false);
    } finally {
        Event::forget($event);
    }

    expect(Project::query()->whereKey([$first->getKey(), $second->getKey()])->count())->toBe(2);
});

it('returns to the first page after archiving the last project on a page', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    for ($index = 0; $index < 16; $index++) {
        app(CreateProject::class)->handle($this->tenant, $kind, $customer);
    }

    $component = Livewire::test(Index::class)->call('setPage', 2);
    $last = $component->instance()->projects->first();

    expect($last)->not->toBeNull();

    $component
        ->set('selected', [$last->getKey()])
        ->call('archiveSelected')
        ->assertSet('paginators.page', 1);

    expect(Project::withTrashed()->find($last->getKey())?->trashed())->toBeTrue();
});

it('toggles the page selection from the header checkbox', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey()]);
    $customer = contactOrganization($this->tenant);

    app(CreateProject::class)->handle($this->tenant, $kind, $customer);
    app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    Livewire::test(Index::class)
        ->call('toggleSelectAll')
        ->assertSet('allSelected', true)
        ->assertSet('someSelected', false)
        ->call('toggleSelectAll')
        ->assertSet('selected', [])
        ->assertSet('allSelected', false);
});
