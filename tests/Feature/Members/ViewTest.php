<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\Role;
use App\Livewire\Members\View;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\Feature\ContactGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('a member can be viewed by uuid', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Viewable Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Viewable Member');
});

test('a member from another organization is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $outside = User::factory()->asMemberOf(Organization::factory())->create();

    get(route('members.show', $outside->person))->assertNotFound();
});

test('the member detail page shows the membership role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Admin)->create(['name' => 'Detail Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Detail Member')
        ->assertSee(__('Admin'));
});

test('the member view lists projects assigned to the member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'assignee_id' => $member->person->getKey(),
        'title' => 'Assigned Member Project',
    ]);
    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'title' => 'Unassigned Member Project',
    ]);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Assigned Member Project')
        ->assertDontSee('Unassigned Member Project');
});

test('the member project list is paginated', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    foreach (range(1, 11) as $index) {
        Project::factory()->create([
            'owner_organization_id' => $organization->getKey(),
            'assignee_id' => $member->person->getKey(),
            'title' => \sprintf('Paged Project %02d', $index),
            'created_at' => now()->subMinutes(11 - $index),
        ]);
    }

    Livewire::test(View::class, ['person' => $member->person])
        ->assertSee('Paged Project 11')
        ->assertDontSee('Paged Project 01')
        ->call('gotoPage', 2)
        ->assertSee('Paged Project 01')
        ->assertDontSee('Paged Project 11');
});

test('the member view shows gender when the workspace enables it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    ContactGender::activate($organization);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->update(['gender' => Gender::Female]);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee(__('Gender'))
        ->assertSee(__('Female'));
});
