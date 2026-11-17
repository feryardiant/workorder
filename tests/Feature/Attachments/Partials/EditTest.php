<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attachments\Partials\Edit as AttachmentEdit;
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

it('updates an attachment title', function (): void {
    /** @var TestCase $this */
    $project = Project::factory()->create([
        'owner_organization_id' => $this->tenant->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($this->tenant)->getKey(),
    ]);

    $attachment = Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'name' => 'report.pdf',
        'mime' => 'application/pdf',
    ]);

    Livewire::test(AttachmentEdit::class)
        ->call('open', $attachment->getKey())
        ->set('title', 'Quarterly report')
        ->call('save')
        ->assertHasNoErrors();

    expect($attachment->refresh()->title)->toBe('Quarterly report');
});
