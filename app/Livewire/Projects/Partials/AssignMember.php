<?php

declare(strict_types=1);

namespace App\Livewire\Projects\Partials;

use App\Enums\TaskAction;
use App\Events\Projects\TaskChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Assigns an internal member to a task.
 *
 * @property-read Collection<int, Person> $members
 */
class AssignMember extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    #[Locked]
    public ?int $taskId = null;

    public ?int $memberId = null;

    #[On('assign-member')]
    public function open(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->status->isCanceled(), 403);

        $this->taskId = $task->getKey();
        $this->memberId = null;
        $this->showModal = true;
    }

    /**
     * Members other than the current assignee.
     *
     * @return Collection<int, Person>
     */
    #[Computed]
    public function members(): Collection
    {
        $assigneeId = $this->taskId === null ? null : $this->findTask($this->taskId)->assignee_id;

        return $this->requireOrganization()->members()->get()
            ->reject(fn (Person $person): bool => $person->getKey() === $assigneeId)
            ->values();
    }

    public function assign(): void
    {
        $task = $this->findTask((int) $this->taskId);
        $member = $this->members->firstWhere('id', $this->memberId);

        abort_if($member === null, 422);

        $previous = $task->assignee?->name;

        $task->update(['assignee_id' => $member->getKey()]);

        event(new TaskChanged(
            $task->project->ownerOrganization,
            Auth::user(),
            $task,
            TaskAction::AssignedMember,
            $previous,
            $member->name,
        ));

        $this->reset('showModal', 'taskId', 'memberId');

        $this->dispatch('task-updated');

        Flux::toast(variant: 'success', text: __('Member assigned.'));
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.projects.partials.assign-member');
    }

    private function findTask(int $taskId): Task
    {
        $organization = $this->requireOrganization();

        return Task::query()
            ->whereKey($taskId)
            ->whereHas('project', fn ($query) => $query->where('owner_organization_id', $organization->getKey()))
            ->firstOrFail();
    }
}
