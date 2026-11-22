<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('keeps the attachment preview modal inside the viewport', function (int $width, int $height): void {
    $owner = browserMember(Role::Owner);
    $organization = Organization::query()->firstOrFail();

    $path = 'attachments/'.Str::uuid().'.pdf';
    $disk = Storage::disk();
    $disk->put($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

    $project = Project::factory()->create([
        'owner_organization_id' => $organization->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($organization)->getKey(),
    ]);

    Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'name' => 'report.pdf',
        'path' => $path,
        'mime' => 'application/pdf',
    ]);

    actingAs($owner);

    try {
        visit('/attachments')
            ->resize($width, $height)
            ->click('[wire\:click*="edit-attachment"]')
            ->wait(0.5)
            ->assertScript(<<<'JS'
                (() => {
                    const dialog = document.querySelector('dialog[open]');

                    if (dialog === null) {
                        return false;
                    }

                    const modal = dialog.getBoundingClientRect();
                    const save = dialog.querySelector('button[wire\\:click="save"]');

                    return modal.left >= 0
                        && modal.right <= window.innerWidth
                        && save !== null
                        && save.getBoundingClientRect().bottom <= window.innerHeight;
                })()
                JS)
            ->assertNoJavaScriptErrors();

        assertNoBackendErrors();
    } finally {
        $disk->delete($path);
    }
})->with([[768, 1024], [1280, 800]]);
