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
 * Edits one draft sub-task of a project kind.
 *
 * @property-read Collection<int, Organization> $contacts
 */
class SubtaskForm extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    #[Locked]
    public ?int $index = null;

    public string $title = '';

    public string $summary = '';

    public ?int $contactId = null;

    /**
     * @param  array{title?: string, summary?: string, contact_id?: ?int}  $subtask
     */
    #[On('open-kind-subtask')]
    public function open(?int $index, array $subtask = []): void
    {
        $this->index = $index;
        $this->title = (string) ($subtask['title'] ?? '');
        $this->summary = (string) ($subtask['summary'] ?? '');
        $this->contactId = $subtask['contact_id'] ?? null;
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

        $this->dispatch('kind-subtask-saved', index: $this->index, subtask: [
            'title' => $this->title,
            'summary' => $this->summary,
            'contact_id' => $this->contactId,
        ]);

        $this->reset('showModal', 'index', 'title', 'summary', 'contactId');
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.project-kinds.partials.subtask-form');
    }
}
