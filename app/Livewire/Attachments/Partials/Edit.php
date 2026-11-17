<?php

declare(strict_types=1);

namespace App\Livewire\Attachments\Partials;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Edits an attachment's title and previews the file.
 *
 * @property-read ?Attachment $attachment
 */
class Edit extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    #[Locked]
    public ?int $attachmentId = null;

    public string $title = '';

    #[On('edit-attachment')]
    public function open(int $attachmentId): void
    {
        $attachment = $this->findAttachment($attachmentId);

        $this->attachmentId = $attachment->getKey();
        $this->title = $attachment->title;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate(['title' => ['required', 'string', 'max:255']]);

        $this->findAttachment((int) $this->attachmentId)->update(['title' => $this->title]);

        $this->reset('showModal', 'attachmentId', 'title');

        $this->dispatch('attachment-updated');

        Flux::toast(variant: 'success', text: __('Attachment updated.'));
    }

    #[Computed]
    public function attachment(): ?Attachment
    {
        return $this->attachmentId === null ? null : $this->findAttachment($this->attachmentId);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.attachments.partials.edit', [
            'attachment' => $this->attachment,
        ]);
    }

    private function findAttachment(int $attachmentId): Attachment
    {
        $organization = $this->requireOrganization();

        $projectIds = Project::query()
            ->where('owner_organization_id', $organization->getKey())
            ->pluck('id');

        $taskIds = Task::query()->whereIn('project_id', $projectIds)->pluck('id');

        return Attachment::query()
            ->whereKey($attachmentId)
            ->where(function (Builder $query) use ($projectIds, $taskIds): void {
                $query->where(function (Builder $query) use ($projectIds): void {
                    $query->where('attachable_type', (new Project)->getMorphClass())
                        ->whereIn('attachable_id', $projectIds);
                })->orWhere(function (Builder $query) use ($taskIds): void {
                    $query->where('attachable_type', (new Task)->getMorphClass())
                        ->whereIn('attachable_id', $taskIds);
                });
            })
            ->firstOrFail();
    }
}
