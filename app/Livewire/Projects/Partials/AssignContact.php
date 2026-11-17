<?php

declare(strict_types=1);

namespace App\Livewire\Projects\Partials;

use App\Enums\OrganizationRelationshipType;
use App\Events\Projects\TaskChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
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
 * Assigns an external vendor contact to a task.
 *
 * @property-read Collection<int, Organization> $contacts
 */
class AssignContact extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    #[Locked]
    public ?int $taskId = null;

    public ?int $contactId = null;

    #[On('assign-contact')]
    public function open(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->status->isCanceled(), 403);

        $this->taskId = $task->getKey();
        $this->contactId = $task->contact_id;
        $this->showModal = true;
    }

    /**
     * Vendor contacts of the current tenant.
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->requireOrganization()
            ->organizationCounterparties(OrganizationRelationshipType::Vendor)
            ->get();
    }

    public function assign(): void
    {
        $task = $this->findTask((int) $this->taskId);
        $contact = $this->contacts->firstWhere('id', $this->contactId);

        abort_if($contact === null, 422);

        $previous = $task->contact?->name;

        $task->update(['contact_id' => $contact->getKey()]);

        event(new TaskChanged($task->project->ownerOrganization, Auth::user(), $task, [
            'action' => 'assigned_contact',
            'from' => $previous,
            'to' => $contact->name,
        ]));

        $this->reset('showModal', 'taskId', 'contactId');

        $this->dispatch('task-updated');

        Flux::toast(variant: 'success', text: __('Contact assigned.'));
    }

    #[On('contact-updated')]
    public function refreshContacts(?int $contactId = null): void
    {
        unset($this->contacts);

        if ($contactId !== null) {
            $this->contactId = $contactId;
        }
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.projects.partials.assign-contact');
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
