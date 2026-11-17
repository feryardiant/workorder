<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();
    actingAs(User::factory()->create());
});

it('returns not found for a missing file', function () {
    get(route('uploads', ['path' => 'avatars/not-exists.jpg']))->assertNotFound();
});

it('returns not found for invalid paths', function () {
    app(FileStorage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    get(route('uploads', ['path' => 'not-avatar/image.jpg']))->assertNotFound();
});

it('streams the file', function () {
    $path = app(FileStorage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    get(route('uploads', ['path' => $path]))
        ->assertStreamed()
        ->assertOk();
});

it('streams an attachment without a presigned round trip', function () {
    $tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($tenant, Role::Member);

    $project = Project::factory()->create([
        'owner_organization_id' => $tenant->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($tenant)->getKey(),
    ]);

    $path = app(FileStorage::class)->handle(UploadedFile::fake()->image('brief.jpg'), 'attachments');

    Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'path' => $path,
    ]);

    get(route('uploads', ['path' => $path]))
        ->assertStreamed()
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('does not stream another tenant attachment', function () {
    /** @var TestCase $this */
    Storage::fake();

    $tenant = Organization::factory()->create();
    $member = actingAsMember($tenant, Role::Member);

    $other = Organization::factory()->asTenantOf($tenant)->create();
    $project = Project::factory()->create([
        'owner_organization_id' => $other->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($other)->getKey(),
    ]);

    $path = 'attachments/private.pdf';
    Storage::disk()->put($path, 'secret');

    Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'path' => $path,
    ]);

    get(route('uploads', ['path' => $path]))->assertNotFound();

    expect($member)->not->toBeNull();
});

it('returns not found for an archived project attachment', function () {
    /** @var TestCase $this */
    Storage::fake();

    $tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($tenant, Role::Member);

    $project = Project::factory()->create([
        'owner_organization_id' => $tenant->getKey(),
        'kind_id' => ProjectKind::factory(),
        'customer_id' => contactOrganization($tenant)->getKey(),
    ]);

    $path = 'attachments/archived.pdf';
    Storage::disk()->put($path, 'secret');

    Attachment::factory()->create([
        'attachable_type' => $project->getMorphClass(),
        'attachable_id' => $project->getKey(),
        'path' => $path,
    ]);

    $project->delete();

    get(route('uploads', ['path' => $path]))->assertNotFound();
});
