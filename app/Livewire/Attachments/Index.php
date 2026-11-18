<?php

declare(strict_types=1);

namespace App\Livewire\Attachments;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Attachment> $attachments
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Task> $tasks
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    public string $search = '';

    public ?string $projectId = null;

    public ?string $taskId = null;

    public ?string $type = null;

    /**
     * @return LengthAwarePaginator<int, Attachment>
     */
    #[Computed]
    public function attachments(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $projectIds = Project::query()->where('owner_organization_id', $organization->getKey())->pluck('id');
        $taskIds = Task::query()->whereIn('project_id', $projectIds)->pluck('id');

        return Attachment::query()
            ->where(function (Builder $query) use ($projectIds, $taskIds): void {
                $query->where(function (Builder $query) use ($projectIds): void {
                    $query->where('attachable_type', (new Project)->getMorphClass())
                        ->whereIn('attachable_id', $projectIds);
                })->orWhere(function (Builder $query) use ($taskIds): void {
                    $query->where('attachable_type', (new Task)->getMorphClass())
                        ->whereIn('attachable_id', $taskIds);
                });
            })
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where('name', 'ilike', '%'.$this->search.'%'))
            ->when(filled($this->projectId), function (Builder $query): void {
                $taskIdsOfProject = Task::query()->where('project_id', (int) $this->projectId)->pluck('id');

                $query->where(function (Builder $query) use ($taskIdsOfProject): void {
                    $query->where(function (Builder $query): void {
                        $query->where('attachable_type', (new Project)->getMorphClass())
                            ->where('attachable_id', (int) $this->projectId);
                    })->orWhere(function (Builder $query) use ($taskIdsOfProject): void {
                        $query->where('attachable_type', (new Task)->getMorphClass())
                            ->whereIn('attachable_id', $taskIdsOfProject);
                    });
                });
            })
            ->when(filled($this->taskId), fn (Builder $query): Builder => $query
                ->where('attachable_type', (new Task)->getMorphClass())
                ->where('attachable_id', (int) $this->taskId))
            ->when($this->type !== null, fn (Builder $query): Builder => $this->applyType($query))
            ->with('attachable')
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new Collection;
        }

        return Project::query()
            ->where('owner_organization_id', $organization->getKey())
            ->orderBy('title')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return Task::query()
            ->whereIn('project_id', $this->projects->pluck('id'))
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function hasAttachments(): bool
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        $projectIds = Project::query()->where('owner_organization_id', $organization->getKey())->pluck('id');
        $taskIds = Task::query()->whereIn('project_id', $projectIds)->pluck('id');

        return Attachment::query()
            ->where(function (Builder $query) use ($projectIds, $taskIds): void {
                $query->where(function (Builder $query) use ($projectIds): void {
                    $query->where('attachable_type', (new Project)->getMorphClass())->whereIn('attachable_id', $projectIds);
                })->orWhere(function (Builder $query) use ($taskIds): void {
                    $query->where('attachable_type', (new Task)->getMorphClass())->whereIn('attachable_id', $taskIds);
                });
            })
            ->exists();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }

    public function updatedTaskId(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    #[On('attachment-updated')]
    public function refreshAttachments(): void
    {
        unset($this->attachments);
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.attachments.index', [
            'attachments' => $this->attachments,
            'projects' => $this->projects,
            'tasks' => $this->tasks,
        ])->title(__('Attachments'));
    }

    /**
     * @param  Builder<Attachment>  $query
     * @return Builder<Attachment>
     */
    private function applyType(Builder $query): Builder
    {
        return match ($this->type) {
            'image' => $query->where('mime', 'like', 'image/%'),
            'pdf' => $query->where('mime', 'application/pdf'),
            'document' => $query->where('mime', 'not like', 'image/%')->where('mime', '!=', 'application/pdf'),
            default => $query,
        };
    }
}
