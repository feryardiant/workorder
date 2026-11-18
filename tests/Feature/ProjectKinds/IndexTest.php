<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\ProjectKinds\Index as ProjectKindIndex;
use App\Models\Organization;
use App\Models\ProjectKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('lists only the current tenant kinds', function (): void {
    /** @var TestCase $this */
    $other = Organization::factory()->asTenantOf($this->tenant)->create();

    ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Mine']);
    ProjectKind::factory()->create(['owner_organization_id' => $other->getKey(), 'title' => 'Theirs']);

    Livewire::test(ProjectKindIndex::class)
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});
