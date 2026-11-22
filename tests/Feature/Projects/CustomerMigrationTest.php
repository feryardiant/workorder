<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('refuses to roll back while projects have individual customers', function (): void {
    /** @var TestCase $this */
    $tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($tenant, Role::Owner);

    $customer = contactIndividual($tenant);

    Project::factory()->create([
        'owner_organization_id' => $tenant->getKey(),
        'customer_id' => $customer->getKey(),
        'customer_type' => $customer->getMorphClass(),
    ]);

    $migration = require database_path('migrations/2026_11_22_122139_add_customer_type_to_projects_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::hasColumn('projects', 'customer_type'))->toBeTrue();
});
