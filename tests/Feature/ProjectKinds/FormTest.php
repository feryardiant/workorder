<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\ProjectKinds\Form as ProjectKindForm;
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
it('stores contacts on kind tasks and copies them to project tasks', function (): void {
    /** @var TestCase $this */
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
it('shows contacts on the project kind task table', function (): void {
    /** @var TestCase $this */
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

it('renders the project kind form', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Renders']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'Kickoff']);

    Livewire::test(ProjectKindForm::class, ['kind' => $kind->getKey()])
        ->assertOk()
        ->assertSee('Kickoff');
});
