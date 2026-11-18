<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attachments\Index as AttachmentIndex;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Project;
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

it('lists only tenant attachments and filters them', function (): void {
    /** @var TestCase $this */
    $project = Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($this->tenant)->getKey(),
    ]);

    Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'name' => 'report.pdf',
        'mime' => 'application/pdf',
    ]);

    $other = Project::factory()->create();
    Attachment::factory()->create([
        'attachable_type' => $other->getMorphClass(),
        'attachable_id' => $other->getKey(),
        'name' => 'stranger.png',
        'mime' => 'image/png',
    ]);

    Livewire::test(AttachmentIndex::class)
        ->assertSee('report.pdf')
        ->assertDontSee('stranger.png')
        ->set('type', 'image')
        ->assertDontSee('report.pdf')
        ->set('type', 'pdf')
        ->assertSee('report.pdf');
});
