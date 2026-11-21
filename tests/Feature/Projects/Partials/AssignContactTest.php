<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Projects\Partials\AssignContact;
use App\Livewire\Projects\View;
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

it('assigns a vendor contact and records the change', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Theta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $contact = contactOrganization($this->tenant, type: OrganizationRelationshipType::Vendor);

    Livewire::test(AssignContact::class)
        ->call('open', $task->getKey())
        ->set('contactId', $contact->getKey())
        ->call('assign')
        ->assertHasNoErrors();

    expect($task->refresh()->contact_id)->toBe($contact->getKey());

    Livewire::test(View::class, ['project' => $project->fresh()])
        ->assertSee(__(':Task contact changed from :from to :to', [
            'task' => $task->title,
            'from' => __('unassigned'),
            'to' => $contact->name,
        ]));
});
