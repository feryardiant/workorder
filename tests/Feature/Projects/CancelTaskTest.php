<?php

declare(strict_types=1);

use App\Actions\Projects\CancelTask;
use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('cancels the tasks below a canceled task and the project itself', function (): void {
    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Beta']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1, 'title' => 'First']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 2, 'title' => 'Second']);

    $customer = contactOrganization($this->tenant);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, $customer);

    $first = $project->tasks->first();
    $second = $project->tasks->last();

    app(CancelTask::class)->handle($first, 'Customer withdrew');

    expect($first->refresh()->canceled_at)->not->toBeNull()
        ->and($project->refresh()->canceled_date)->not->toBeNull()
        ->and($project->remark)->toBe('Customer withdrew')
        ->and($second->refresh()->isDisabled())->toBeTrue();

    expect(fn () => app(CancelTask::class)->handle($second, '   '))
        ->toThrow(ValidationException::class);
});
