<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\FindContact;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Project;
use App\Support\Feature\ContactGender;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class View extends Component
{
    use HasCurrentOrganization, WithPagination;

    #[Locked]
    public OrganizationRelationship $contact;

    public function mount(string $stakeholder): void
    {
        $this->contact = app(FindContact::class)->byUuid($this->requireOrganization(), $stakeholder);

        Gate::authorize('viewContact', $this->contact->stakeholder);
    }

    /**
     * The relationship's full stint history.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    #[Computed]
    public function history(): Collection
    {
        return OrganizationRelationshipPeriod::query()
            ->where('organization_id', $this->contact->organization_id)
            ->where('stakeholder_type', $this->contact->stakeholder_type)
            ->where('stakeholder_id', $this->contact->stakeholder_id)
            ->with('actor')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Projects tied to the contact: assigned to a person, or ordered by an organization customer.
     *
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $stakeholder = $this->contact->stakeholder;

        if ($stakeholder === null) {
            return new LengthAwarePaginator([], 0, 10);
        }

        return Project::query()
            ->where('owner_organization_id', $this->requireOrganization()->getKey())
            ->when(
                $stakeholder instanceof Organization,
                fn ($query) => $query->where('customer_id', $stakeholder->getKey()),
                fn ($query) => $query->where('assignee_id', $stakeholder->getKey()),
            )
            ->with(['kind', 'customer', 'assignee'])
            ->latest('created_at')
            ->paginate(10);
    }

    public function render(): ViewContract
    {
        $stakeholder = $this->contact->stakeholder;

        abort_if($stakeholder === null, 404);

        /** @disregard P1013 */
        return view('livewire.contacts.view', [
            'stakeholder' => $stakeholder,
            'isOrganization' => $this->contact->isOrganization(),
            'contactPerson' => $stakeholder instanceof Organization && ! $stakeholder->trashed() ? $stakeholder->primaryContact : null,
            'showGender' => ContactGender::enabled($this->currentOrganization()),
        ])->title(__('Contact details'));
    }
}
