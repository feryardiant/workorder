<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('refreshes the project history after a task starts without reloading', function () {
    $owner = browserMember(Role::Owner);
    $organization = Organization::query()->firstOrFail();

    actingAs($owner);

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $organization->getKey()]);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Polled task']);

    $project = app(CreateProject::class)->handle($organization, $kind, contactOrganization($organization));

    visit(route('projects.view', $project))
        ->assertSee(__('Project histories'))
        ->click('Mulai tugas')
        ->wait(6)
        ->assertSee(__(':Task started', ['task' => 'Polled task']));

    assertNoBackendErrors();
});
