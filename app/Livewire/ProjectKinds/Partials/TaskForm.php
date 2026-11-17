<?php

declare(strict_types=1);

namespace App\Livewire\ProjectKinds\Partials;

use App\Enums\OrganizationRelationshipType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Edits one draft task of a project kind, including its sub-tasks.
 *
 * @property-read Collection<int, Organization> $contacts
 */
class TaskForm extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    #[Locked]
    public ?int $index = null;

    public string $title = '';

    public string $summary = '';

    public ?int $contactId = null;

    /**
     * @var list<array{title: string, summary: string, contact_id: ?int}>
     */
    public array $subtasks = [];

    /**
     * @param  array{title?: string, summary?: string, contact_id?: ?int, subtasks?: list<array{title: string, summary: string, contact_id: ?int}>}  $task
     */
    #[On('open-kind-task')]
    public function open(?int $index, array $task = []): void
    {
        $this->index = $index;
        $this->title = (string) ($task['title'] ?? '');
        $this->summary = (string) ($task['summary'] ?? '');
        $this->contactId = $task['contact_id'] ?? null;
        $this->subtasks = $task['subtasks'] ?? [];
        $this->showModal = true;
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->requireOrganization()
            ->organizationCounterparties(OrganizationRelationshipType::Vendor)
            ->get();
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($this->subtasks as $subtask) {
            if (trim($subtask['title']) === '') {
                $this->addError('subtasks', __('Every sub-task needs a title.'));

                return;
            }
        }

        $this->dispatch('kind-task-saved', index: $this->index, task: [
            'title' => $this->title,
            'summary' => $this->summary,
            'contact_id' => $this->contactId,
            'subtasks' => $this->subtasks,
        ]);

        $this->reset('showModal', 'index', 'title', 'summary', 'contactId', 'subtasks');
    }

    public function addSubtask(): void
    {
        $this->dispatch('open-kind-subtask', index: null, subtask: []);
    }

    public function editSubtask(int $index): void
    {
        $subtask = $this->subtasks[$index] ?? null;

        abort_if($subtask === null, 404);

        $this->dispatch('open-kind-subtask', index: $index, subtask: $subtask);
    }

    /**
     * @param  array{title?: string, summary?: string, contact_id?: ?int}  $subtask
     */
    #[On('kind-subtask-saved')]
    public function applySubtask(?int $index, array $subtask): void
    {
        $value = [
            'title' => (string) ($subtask['title'] ?? ''),
            'summary' => (string) ($subtask['summary'] ?? ''),
            'contact_id' => $subtask['contact_id'] ?? null,
        ];

        if ($index === null) {
            $this->subtasks[] = $value;
        } else {
            $this->subtasks[$index] = $value;
        }
    }

    public function deleteSubtask(int $index): void
    {
        array_splice($this->subtasks, $index, 1);
    }

    public function moveSubtask(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->subtasks[$target])) {
            return;
        }

        [$this->subtasks[$index], $this->subtasks[$target]] = [$this->subtasks[$target], $this->subtasks[$index]];
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
        return view('livewire.project-kinds.partials.task-form');
    }
}
