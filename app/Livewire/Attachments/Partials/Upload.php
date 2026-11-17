<?php

declare(strict_types=1);

namespace App\Livewire\Attachments\Partials;

use App\Actions\FileStorage;
use App\Events\Projects\AttachmentChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Manages the attachments of a project or a top-level task.
 *
 * @property-read Project|Task|null $attachable
 * @property-read Collection<int, Attachment> $attachments
 */
class Upload extends Component
{
    use HasCurrentOrganization, WithFileUploads;

    public bool $showModal = false;

    #[Locked]
    public ?string $attachableType = null;

    #[Locked]
    public ?int $attachableId = null;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $uploads = [];

    #[On('add-attachments')]
    public function open(string $type, int $id): void
    {
        $attachable = $this->resolveAttachable($type, $id);

        abort_if($attachable instanceof Task && $attachable->isSubTask(), 403);

        $this->assertWritable($attachable);

        $this->attachableType = $attachable->getMorphClass();
        $this->attachableId = $attachable->getKey();
        $this->uploads = [];
        $this->showModal = true;
    }

    #[Computed]
    public function attachable(): Project|Task|null
    {
        if ($this->attachableType === null || $this->attachableId === null) {
            return null;
        }

        return $this->attachableType === (new Project)->getMorphClass()
            ? Project::query()->find($this->attachableId)
            : Task::query()->find($this->attachableId);
    }

    /**
     * @return Collection<int, Attachment>
     */
    #[Computed]
    public function attachments(): Collection
    {
        $attachable = $this->attachable;

        if ($attachable === null) {
            return new Collection;
        }

        return $attachable->attachments()
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function removeUpload(int $index): void
    {
        if (! isset($this->uploads[$index])) {
            return;
        }

        array_splice($this->uploads, $index, 1);
    }

    public function save(FileStorage $fileStorage): void
    {
        $this->validate([
            'uploads.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp'],
        ]);

        $attachable = $this->attachable;

        abort_if($attachable === null, 422);

        $this->assertWritable($attachable);

        $count = 0;

        foreach ($this->uploads as $upload) {
            $path = $fileStorage->handle($upload, 'attachments');

            $attachable->attachments()->create([
                'title' => $upload->getClientOriginalName(),
                'name' => $upload->getClientOriginalName(),
                'path' => $path,
                'mime' => $upload->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $upload->getSize(),
            ]);

            $count++;
        }

        if ($count > 0) {
            event(new AttachmentChanged($this->organizationFor($attachable), Auth::user(), $attachable, [
                'action' => 'added',
                'count' => $count,
            ]));
        }

        $this->reset('uploads', 'showModal', 'attachableType', 'attachableId');

        $this->dispatch('attachments-updated');

        Flux::toast(variant: 'success', text: __('Attachments uploaded.'));
    }

    public function remove(int $attachmentId, FileStorage $fileStorage): void
    {
        $attachment = $this->attachments->firstWhere('id', $attachmentId);

        abort_if($attachment === null, 404);

        $attachable = $this->attachable;

        abort_if($attachable === null, 404);

        $this->assertWritable($attachable);

        $fileStorage->delete($attachment->path);

        $attachment->delete();

        $attachable = $this->attachable;

        if ($attachable !== null) {
            event(new AttachmentChanged($this->organizationFor($attachable), Auth::user(), $attachable, [
                'action' => 'removed',
                'count' => 1,
                'name' => $attachment->name,
            ]));
        }

        unset($this->attachments);

        $this->dispatch('attachments-updated');
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.attachments.partials.upload');
    }

    private function assertWritable(Project|Task $attachable): void
    {
        abort_if($attachable->status->isCanceled() || $attachable->status->isFinished(), 403);

        if ($attachable instanceof Task) {
            abort_if($attachable->isDisabled() || $attachable->status->isCompleted(), 403);
        }
    }

    private function organizationFor(Project|Task $attachable): Organization
    {
        return $attachable instanceof Project
            ? $attachable->ownerOrganization
            : $attachable->project->ownerOrganization;
    }

    private function resolveAttachable(string $type, int $id): Project|Task
    {
        $organization = $this->requireOrganization();

        return match ($type) {
            'project' => Project::query()
                ->where('owner_organization_id', $organization->getKey())
                ->findOrFail($id),
            'task' => Task::query()
                ->whereKey($id)
                ->whereHas('project', fn ($query) => $query->where('owner_organization_id', $organization->getKey()))
                ->firstOrFail(),
            default => abort(404),
        };
    }
}
