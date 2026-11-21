<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Enums\TaskAction;
use App\Events\Projects\TaskChanged;
use App\Livewire\Projects\Partials\History;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

function projectForHistory(Organization $tenant): Project
{
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $tenant->getKey()]);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);

    return app(CreateProject::class)->handle($tenant, $kind, contactOrganization($tenant));
}

it('renders the project history and polls for updates', function (): void {
    /** @var TestCase $this */
    $project = projectForHistory($this->tenant);

    Livewire::test(History::class, ['project' => $project])
        ->assertSee(__('Project histories'))
        ->assertSee(__('Project created'))
        ->assertSee('wire:poll.5s.visible', false);
});

it('includes the history of the project tasks', function (): void {
    /** @var TestCase $this */
    $project = projectForHistory($this->tenant);
    $task = $project->tasks->first();

    event(new TaskChanged($this->tenant, Auth::user(), $task, TaskAction::Finished));

    Livewire::test(History::class, ['project' => $project])
        ->assertSee(__(':Task finished', ['task' => $task->title]));
});

it('shows entries recorded after the component has mounted', function (): void {
    /** @var TestCase $this */
    $project = projectForHistory($this->tenant);
    $task = $project->tasks->first();

    $component = Livewire::test(History::class, ['project' => $project])
        ->assertDontSee(__(':Task started', ['task' => $task->title]));

    event(new TaskChanged($this->tenant, Auth::user(), $task, TaskAction::Started));

    $component->call('$refresh')
        ->assertSee(__(':Task started', ['task' => $task->title]));
});

it('only lists the history of the given project', function (): void {
    /** @var TestCase $this */
    $project = projectForHistory($this->tenant);
    projectForHistory($this->tenant);

    expect(Livewire::test(History::class, ['project' => $project])->instance()->timeline)->toHaveCount(1);
});

it('forbids a project from another organization', function (): void {
    /** @var TestCase $this */
    $project = projectForHistory($this->tenant);

    $other = Organization::factory()->create();
    actingAsMember($other, Role::Owner);

    Livewire::test(History::class, ['project' => $project])->assertStatus(404);
});
