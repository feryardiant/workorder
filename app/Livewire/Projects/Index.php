<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\OrganizationRelationshipType;
use App\Enums\ProjectStatus;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectKind;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Project> $projects
 * @property-read Collection<int, ProjectKind> $kinds
 * @property-read Collection<int, Organization> $customers
 * @property-read Collection<int, Person> $members
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    public string $search = '';

    public ?string $kindId = null;

    public ?string $customerId = null;

    public ?string $status = null;

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $startedRange = ['start' => null, 'end' => null];

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $finishedRange = ['start' => null, 'end' => null];

    public bool $showCreate = false;

    public ?int $createKindId = null;

    public ?int $createCustomerId = null;

    public ?int $createAssigneeId = null;

    public string $createSummary = '';

    public ?string $createMandatedDate = null;

    public ?string $createStartedDate = null;

    public bool $showArchive = false;

    public ?int $archivingId = null;

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        return Project::query()
            ->where('owner_organization_id', $organization->getKey())
            ->with(['kind', 'customer', 'assignee'])
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('title', 'ilike', '%'.$this->search.'%')
                    ->orWhere('summary', 'ilike', '%'.$this->search.'%'),
            ))
            ->when(filled($this->kindId), fn (Builder $query): Builder => $query->where('kind_id', (int) $this->kindId))
            ->when(filled($this->customerId), fn (Builder $query): Builder => $query->where('customer_id', (int) $this->customerId))
            ->when($this->status !== null, fn (Builder $query): Builder => $this->applyStatus($query))
            ->when(filled($this->startedRange['start'] ?? null), fn (Builder $query): Builder => $query->whereDate('started_date', '>=', $this->startedRange['start']))
            ->when(filled($this->startedRange['end'] ?? null), fn (Builder $query): Builder => $query->whereDate('started_date', '<=', $this->startedRange['end']))
            ->when(filled($this->finishedRange['start'] ?? null), fn (Builder $query): Builder => $query->whereDate('finished_date', '>=', $this->finishedRange['start']))
            ->when(filled($this->finishedRange['end'] ?? null), fn (Builder $query): Builder => $query->whereDate('finished_date', '<=', $this->finishedRange['end']))
            ->latest('created_at')
            ->paginate(15);
    }

    /**
     * @return Collection<int, ProjectKind>
     */
    #[Computed]
    public function kinds(): Collection
    {
        return ProjectKind::query()->orderBy('title')->get();
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function customers(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new Collection;
        }

        return $organization->organizationCounterparties(OrganizationRelationshipType::Customer)->get();
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function members(): Collection
    {
        $organization = $this->currentOrganization();

        return $organization === null ? new Collection : $organization->members()->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedKindId(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[On('contact-updated')]
    public function refreshCustomers(?int $contactId = null): void
    {
        unset($this->customers);

        if ($contactId !== null) {
            $this->createCustomerId = $contactId;
        }
    }

    public function create(CreateProject $createProject): void
    {
        $organization = $this->requireOrganization();

        $this->validate([
            'createKindId' => ['required', 'integer'],
            'createCustomerId' => ['required', 'integer'],
            'createAssigneeId' => ['required', 'integer'],
            'createSummary' => ['nullable', 'string', 'max:5000'],
            'createMandatedDate' => ['nullable', 'date'],
            'createStartedDate' => ['nullable', 'date'],
        ]);

        $kind = $this->kinds->firstWhere('id', $this->createKindId);
        $customer = $this->customers->firstWhere('id', $this->createCustomerId);
        $assignee = $this->members->firstWhere('id', $this->createAssigneeId);

        abort_if($kind === null || $customer === null || $assignee === null, 422);

        $project = $createProject->handle($organization, $kind, $customer, $assignee, [
            'summary' => $this->createSummary === '' ? null : $this->createSummary,
            'mandated_date' => $this->createMandatedDate,
            'started_date' => $this->createStartedDate,
        ], Auth::user());

        $this->reset(
            'showCreate',
            'createKindId',
            'createCustomerId',
            'createAssigneeId',
            'createSummary',
            'createMandatedDate',
            'createStartedDate',
        );

        Flux::toast(variant: 'success', text: __('Project created.'));

        $this->redirectRoute('projects.view', ['project' => $project->slug], navigate: true);
    }

    public function confirmArchive(int $projectId): void
    {
        $this->archivingId = $projectId;
        $this->showArchive = true;
    }

    public function archive(): void
    {
        $organization = $this->requireOrganization();

        $project = Project::query()
            ->where('owner_organization_id', $organization->getKey())
            ->findOrFail($this->archivingId);

        $project->delete();

        $this->reset('showArchive', 'archivingId');

        unset($this->projects);

        Flux::toast(variant: 'success', text: __('Project archived.'));
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.projects.index', [
            'projects' => $this->projects,
            'kinds' => $this->kinds,
            'customers' => $this->customers,
            'members' => $this->members,
            'statuses' => ProjectStatus::cases(),
        ])->title(__('Projects'));
    }

    /**
     * Apply the derived status filter.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function applyStatus(Builder $query): Builder
    {
        return match (ProjectStatus::tryFrom((string) $this->status)) {
            ProjectStatus::Canceled => $query->whereNotNull('canceled_date'),
            ProjectStatus::Finished => $query->whereNull('canceled_date')->whereNotNull('finished_date'),
            ProjectStatus::NotStarted => $query->whereNull('canceled_date')->whereNull('started_date'),
            ProjectStatus::Scheduled => $query->whereNull('canceled_date')->whereDate('started_date', '>', today()),
            ProjectStatus::OnGoing => $query
                ->whereNull('canceled_date')
                ->whereNull('finished_date')
                ->whereDate('started_date', '<=', today()),
            null => $query,
        };
    }
}
