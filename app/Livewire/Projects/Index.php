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
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * @property-read LengthAwarePaginator<int, Project> $projects
 * @property-read Collection<int, ProjectKind> $kinds
 * @property-read Collection<int, Organization|Person> $customers
 * @property-read Collection<int, Person> $members
 * @property-read bool $isManager
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    public string $search = '';

    public ?string $kindId = null;

    public ?string $customerRef = null;

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

    public ?string $createCustomerRef = null;

    public int $customersVersion = 0;

    public ?int $createAssigneeId = null;

    public string $createSummary = '';

    public ?string $createMandatedDate = null;

    public ?string $createStartedDate = null;

    public bool $showArchive = false;

    public ?int $archivingId = null;

    public bool $showBatchArchive = false;

    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public int $selectionKey = 0;

    public bool $allSelected = false;

    public bool $someSelected = false;

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
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as finished_tasks_count' => fn (Builder $query): Builder => $query->whereNotNull('finished_at'),
            ])
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('title', 'ilike', '%'.$this->search.'%')
                    ->orWhere('summary', 'ilike', '%'.$this->search.'%'),
            ))
            ->when(filled($this->kindId), fn (Builder $query): Builder => $query->where('kind_id', (int) $this->kindId))
            ->when(filled($this->customerRef), function (Builder $query): Builder {
                return $this->scopeCustomer($query, (string) $this->customerRef);
            })
            ->when($this->status !== null, fn (Builder $query): Builder => $this->applyStatus($query))
            ->when(filled($this->startedRange['start'] ?? null), fn (Builder $query): Builder => $query->whereDate('started_date', '>=', $this->startedRange['start']))
            ->when(filled($this->startedRange['end'] ?? null), fn (Builder $query): Builder => $query->whereDate('started_date', '<=', $this->startedRange['end']))
            ->when(filled($this->finishedRange['start'] ?? null), fn (Builder $query): Builder => $query->whereDate('finished_date', '>=', $this->finishedRange['start']))
            ->when(filled($this->finishedRange['end'] ?? null), fn (Builder $query): Builder => $query->whereDate('finished_date', '<=', $this->finishedRange['end']))
            ->latest('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function hasProjects(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && Project::query()->where('owner_organization_id', $organization->getKey())->exists();
    }

    #[Computed]
    public function canSelectAny(): bool
    {
        return $this->selectablePageIds() !== [];
    }

    /**
     * @return Collection<int, ProjectKind>
     */
    #[Computed]
    public function kinds(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new Collection;
        }

        return ProjectKind::query()
            ->where('owner_organization_id', $organization->getKey())
            ->orderBy('title')
            ->get();
    }

    /**
     * @return Collection<int, Organization|Person>
     */
    #[Computed]
    public function customers(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new Collection;
        }

        return $organization->counterparties(OrganizationRelationshipType::Customer);
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
        $this->clearSelection();
    }

    public function updatedKindId(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedCustomerRef(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStartedRange(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedFinishedRange(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    public function toggleSelectAll(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === [] ? [] : $pageIds;
        $this->selectionKey++;
        $this->updatedSelected();
    }

    #[On('contact-updated')]
    public function refreshCustomers(?int $contactId = null, ?string $contactType = null): void
    {
        unset($this->customers);
        $this->customersVersion++;

        if ($contactId !== null && $contactType !== null) {
            $this->createCustomerRef = $contactType.':'.$contactId;
        }
    }

    public function create(CreateProject $createProject): void
    {
        $organization = $this->requireOrganization();

        $this->validate([
            'createKindId' => ['required', 'integer'],
            'createCustomerRef' => ['required', 'string'],
            'createAssigneeId' => ['required', 'integer'],
            'createSummary' => ['nullable', 'string', 'max:5000'],
            'createMandatedDate' => ['nullable', 'date'],
            'createStartedDate' => ['nullable', 'date'],
        ]);

        $kind = $this->kinds->firstWhere('id', $this->createKindId);
        $customer = $this->customers->first(
            fn (Organization|Person $customer): bool => $customer->getMorphClass().':'.$customer->getKey() === $this->createCustomerRef,
        );
        $assignee = $this->members->firstWhere('id', $this->createAssigneeId);

        abort_if($kind === null || $customer === null || $assignee === null, 422);

        if (! $this->isManager && $assignee->getKey() !== Auth::user()?->person?->getKey()) {
            $this->addError('createAssigneeId', __('Members can only create projects assigned to themselves.'));

            return;
        }

        $project = $createProject->handle($organization, $kind, $customer, $assignee, [
            'summary' => $this->createSummary === '' ? null : $this->createSummary,
            'mandated_date' => $this->createMandatedDate,
            'started_date' => $this->createStartedDate,
        ], Auth::user());

        $this->reset(
            'showCreate',
            'createKindId',
            'createCustomerRef',
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

        abort_unless($this->canManageProject($project), 403);

        $project->delete();

        $this->reset('showArchive', 'archivingId');

        unset($this->projects);

        Flux::toast(variant: 'success', text: __('Project archived.'));
    }

    public function confirmArchiveSelected(): void
    {
        $this->showBatchArchive = true;
    }

    public function archiveSelected(): void
    {
        $organization = $this->requireOrganization();

        $ids = array_values(array_intersect(array_map('intval', $this->selected), $this->selectablePageIds()));

        if ($ids === []) {
            $this->reset('showBatchArchive');
            $this->clearSelection();

            return;
        }

        $projects = Project::query()
            ->where('owner_organization_id', $organization->getKey())
            ->whereKey($ids)
            ->get();

        if ($projects->isEmpty()) {
            $this->reset('showBatchArchive');
            $this->clearSelection();

            return;
        }

        try {
            DB::transaction(function () use ($projects): void {
                foreach ($projects as $project) {
                    $project->delete();
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->reset('showBatchArchive');

            Flux::toast(variant: 'danger', text: __('Failed to archive the selected projects.'));

            return;
        }

        $this->reset('showBatchArchive');
        $this->resetPage();
        $this->clearSelection();
        unset($this->projects, $this->canSelectAny);

        Flux::toast(
            variant: 'success',
            text: __(':count projects archived successfully.', ['count' => $projects->count()]),
        );
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
     * Owners and admins may manage any project; members only their own.
     */
    private function canManageProject(Project $project): bool
    {
        return $this->isManager || $project->assignee_id === Auth::user()?->person?->getKey();
    }

    /**
     * @return array<int, int>
     */
    private function selectablePageIds(): array
    {
        return $this->projects
            ->filter(fn (Project $project): bool => ! $project->trashed() && $this->canManageProject($project))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function clearSelection(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;
        $this->selectionKey++;
    }

    private function todayDate(): string
    {
        $user = Auth::user();

        return CarbonImmutable::now($user->timezone ?? 'UTC')->toDateString();
    }

    /**
     * Narrow projects to the customer behind a "{type}:{id}" reference.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function scopeCustomer(Builder $query, string $reference): Builder
    {
        [$type, $id] = explode(':', $reference, 2) + [null, null];

        return $query->where('customer_type', $type)->where('customer_id', (int) $id);
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
            ProjectStatus::Archived => $query->onlyTrashed(),
            ProjectStatus::Canceled => $query->whereNotNull('canceled_date'),
            ProjectStatus::Finished => $query->whereNull('canceled_date')->whereNotNull('finished_date'),
            ProjectStatus::NotStarted => $query->whereNull('canceled_date')->whereNull('started_date'),
            ProjectStatus::Scheduled => $query->whereNull('canceled_date')->whereDate('started_date', '>', $this->todayDate()),
            ProjectStatus::OnGoing => $query
                ->whereNull('canceled_date')
                ->whereNull('finished_date')
                ->whereDate('started_date', '<=', $this->todayDate()),
            null => $query,
        };
    }
}
