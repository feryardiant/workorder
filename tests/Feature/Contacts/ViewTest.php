<?php

declare(strict_types=1);

use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\Project;
use App\Support\Feature\ContactGender;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the contact view page renders the contact information', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contactPerson = Person::factory()->withoutCredential()->create([
        'name' => 'View Contact Person',
        'gender' => 'F',
        'summary' => 'Person summary',
    ]);
    $contact = Organization::factory()->create([
        'contact_id' => $contactPerson->getKey(),
        'name' => 'Acme View',
        'summary' => 'Contact summary',
    ]);
    $organization->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Supplier]);

    OrganizationRelationship::forOrganization($organization)
        ->whereStakeholder($contact)
        ->update(['code' => 'VIEW-1', 'start_date' => '2026-01-01']);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('Acme View')
        ->assertSee('VIEW-1')
        ->assertSee(OrganizationRelationshipType::Supplier->label())
        ->assertSee('View Contact Person')
        ->assertSee('Contact summary');
});

test('the contact view page shows an individual gender', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    ContactGender::activate($organization);

    $person = contactIndividual($organization, [
        'name' => 'Gendered Individual',
        'gender' => Gender::Female,
    ]);

    get(route('contacts.view', ['stakeholder' => $person]))
        ->assertOk()
        ->assertSee(__('Female'));
});

test('the contact view page hides the gender when the feature is off', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $person = contactIndividual($organization, [
        'name' => 'Ungendered Individual',
        'gender' => Gender::Female,
    ]);

    get(route('contacts.view', ['stakeholder' => $person]))
        ->assertOk()
        ->assertDontSee(__('Gender'))
        ->assertDontSee(__('Female'));
});

test('a contact from another organization is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $foreign = contactIndividual(Organization::factory()->create(), ['name' => 'Foreign Contact']);

    get(route('contacts.view', ['stakeholder' => $foreign]))->assertNotFound();
});

test('a non-external relationship is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = Person::factory()->withoutCredential()->create(['name' => 'Member Person']);
    $organization->members()->attach($member, [
        'type' => OrganizationRelationshipType::Member,
        'start_date' => now(),
    ]);

    get(route('contacts.view', ['stakeholder' => $member]))->assertNotFound();
});

test('a contact whose stakeholder is soft-deleted is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization, ['name' => 'Deleted View']);
    $contact->delete();

    get(route('contacts.view', ['stakeholder' => $contact]))->assertNotFound();
});

test('an individual contact lists projects assigned to them', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $person = contactIndividual($organization, ['name' => 'Assigned Individual']);

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'assignee_id' => $person->getKey(),
        'title' => 'Assigned Individual Project',
    ]);
    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'title' => 'Unassigned Individual Project',
    ]);

    get(route('contacts.view', ['stakeholder' => $person]))
        ->assertOk()
        ->assertSee('Assigned Individual Project')
        ->assertDontSee('Unassigned Individual Project');
});

test('an organization contact lists projects they are the customer of', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactOrganization($organization, ['name' => 'Project Customer']);
    $assignee = Person::factory()->withoutCredential()->create(['name' => 'Project Handler']);

    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'customer_id' => $contact->getKey(),
        'assignee_id' => $assignee->getKey(),
        'title' => 'Customer Projects',
    ]);
    Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'title' => 'Other Customer Projects',
    ]);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee(__('Assignee'))
        ->assertSee('Project Handler')
        ->assertSee('Customer Projects')
        ->assertDontSee('Other Customer Projects');
});

test('the contact view displays the email and phone', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Detail Individual']);
    $contact->contactDetails()->createMany([
        ['type' => ContactDetailType::Email, 'value' => 'detail@acme.test'],
        ['type' => ContactDetailType::Phone, 'value' => '0812 999'],
    ]);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('detail@acme.test')
        ->assertSee('0812 999');
});
