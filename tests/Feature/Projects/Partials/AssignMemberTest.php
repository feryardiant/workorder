<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Livewire\Projects\Partials\AssignMember;
use App\Livewire\Projects\View;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('excludes the current assignee from the member modal', function (): void {
    /** @var TestCase $this */
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

it('assigns a member and records the reassignment', function (): void {
    /** @var TestCase $this */
    $owner = actingAsMember($this->tenant, Role::Owner);
    $member = $owner->person;

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Theta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();

    Livewire::test(AssignMember::class)
        ->call('open', $task->getKey())
        ->set('memberId', $member?->getKey())
        ->call('assign')
        ->assertHasNoErrors();

    expect($task->refresh()->assignee_id)->toBe($member?->getKey())
        ->and(AuditLog::query()->where('event', AuditEvent::TaskChanged)->count())->toBe(1);

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee(__(':Task reassigned from :from to :to', [
            'task' => $task->title,
            'from' => __('unassigned'),
            'to' => $member?->name,
        ]));
});
