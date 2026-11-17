<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Enums\Role;
use App\Livewire\Attachments\Partials\Upload;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->tenant = Organization::factory()->asRootTenant()->create();
    actingAsMember($this->tenant, Role::Admin);
});

it('uploads attachments for projects and tasks but not sub-tasks', function (): void {
    Storage::fake();

    /** @var TestCase $this */
    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Uploads']);
    $first = ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    ProjectKindTask::factory()->subTaskOf($first)->create(['position' => 1]);

    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));
    $task = $project->tasks->first();
    $subTask = $task->subTasks->first();

    Livewire::test(Upload::class)
        ->call('open', 'project', $project->getKey())
        ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Upload::class)
        ->call('open', 'task', $task->getKey())
        ->set('uploads', [UploadedFile::fake()->image('photo.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect($project->attachments()->count())->toBe(1)
        ->and($task->attachments()->count())->toBe(1);

    $attachment = $task->attachments()->sole();

    Livewire::test(Upload::class)
        ->call('open', 'task', $task->getKey())
        ->call('remove', $attachment->getKey());

    expect($task->attachments()->count())->toBe(0);

    Livewire::test(Upload::class)
        ->call('open', 'task', $subTask->getKey())
        ->assertForbidden();
});

it('rejects oversized files and unsupported types', function (): void {
    /** @var TestCase $this */
    Storage::fake();

    $kind = ProjectKind::factory()->create(['owner_organization_id' => $this->tenant->getKey(), 'title' => 'Limits']);
    ProjectKindTask::factory()->for($kind, 'kind')->create(['position' => 1]);
    $project = app(CreateProject::class)->handle($this->tenant, $kind, contactOrganization($this->tenant));

    Livewire::test(Upload::class)
        ->call('open', 'project', $project->getKey())
        ->set('uploads', [UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')])
        ->call('save')
        ->assertHasErrors('uploads.*');

    Livewire::test(Upload::class)
        ->call('open', 'project', $project->getKey())
        ->set('uploads', [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')])
        ->call('save')
        ->assertHasErrors('uploads.*');

    expect($project->attachments()->count())->toBe(0);
});
