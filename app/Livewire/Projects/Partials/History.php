<?php

declare(strict_types=1);

namespace App\Livewire\Projects\Partials;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, AuditLog> $timeline
 */
class History extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless($project->owner_organization_id === $this->requireOrganization()->getKey(), 404);

        $this->project = $project;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $taskIds = $this->project->allTasks()->pluck('id');

        return AuditLog::query()
            ->where('organization_id', $this->project->owner_organization_id)
            ->where(function ($query) use ($taskIds): void {
                $query->where(function ($query): void {
                    $query->where('subject_type', $this->project->getMorphClass())
                        ->where('subject_id', $this->project->getKey());
                })->orWhere(function ($query) use ($taskIds): void {
                    $query->where('subject_type', (new Task)->getMorphClass())
                        ->whereIn('subject_id', $taskIds);
                });
            })
            ->with(['actor', 'subject'])
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.projects.partials.history');
    }
}
