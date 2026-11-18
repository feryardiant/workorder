<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\ProjectKinds\Partials\SubtaskForm;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('saves a kind sub-task draft and emits it to the parent', function (): void {
    Livewire::test(SubtaskForm::class)
        ->call('open', null, [])
        ->set('title', 'Draft sub-task')
        ->call('save')
        ->assertDispatched('kind-subtask-saved');
});
