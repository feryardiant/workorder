<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Livewire\Projects\Index;
use App\Models\Organization;
use App\Models\Project;
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
