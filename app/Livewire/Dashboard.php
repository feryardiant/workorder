<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrganizationRelationshipType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Workspace overview for the current organization, filtered by a date range.
 *
 * @property-read int $newMembers
 * @property-read int $newContacts
 * @property-read int $expiringCount
 * @property-read int $unreadNotifications
 * @property-read Collection<int, OrganizationRelationship> $expiringContacts
 * @property-read Collection<int, Person> $recentMembers
 * @property-read int $newCustomers
 * @property-read int $onGoingProjects
 * @property-read int $overdueProjects
 * @property-read int $dueThisWeek
 * @property-read Collection<int, Project> $projectsNeedingAttention
 * @property-read Collection<int, Task> $myTasks
 */
class Dashboard extends Component
{
    use HasCurrentOrganization;

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $range = ['start' => null, 'end' => null];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->range = $this->defaultRange();
    }

    /**
     * Normalize the date range.
     */
    public function updatedRange(): void
    {
        $this->range = $this->normalizeRange($this->range);
    }

    /**
     * Members whose membership started inside the range and is still active.
     */
    #[Computed]
    public function newMembers(): int
    {
        return $this->membershipQuery()->count();
    }

    /**
     * External contacts whose relationship started inside the range.
     */
    #[Computed]
    public function newContacts(): int
    {
        return $this->contactQuery()->count();
    }

    /**
     * Active external contacts finishing inside the range.
     */
    #[Computed]
    public function expiringCount(): int
    {
        return $this->expiringQuery()->count();
    }

    #[Computed]
    public function unreadNotifications(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        $timezone = $this->userTimezone();

        return $user->unreadNotifications()
            ->where('created_at', '>=', CarbonImmutable::parse($this->range['start'], $timezone)->startOfDay()->utc())
            ->where('created_at', '<=', CarbonImmutable::parse($this->range['end'], $timezone)->endOfDay()->utc())
            ->count();
    }

    /**
     * @return Collection<int, OrganizationRelationship>
     */
    #[Computed]
    public function expiringContacts(): Collection
    {
        return $this->expiringQuery()
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->withTrashed()])
            ->orderBy('finish_date')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function recentMembers(): Collection
    {
        return $this->membershipQuery()
            ->with('user')
            ->orderByDesc('organization_relationships.start_date')
            ->limit(5)
            ->get();
    }

    /**
     * Customers whose relationship started inside the range.
     */
    #[Computed]
    public function newCustomers(): int
    {
        return $this->contactQuery()
            ->where('type', OrganizationRelationshipType::Customer)
            ->count();
    }

    /**
     * Projects started inside the range that are neither finished nor canceled.
     */
    #[Computed]
    public function onGoingProjects(): int
    {
        return $this->projectQuery()
            ->whereDate('started_date', '>=', $this->range['start'])
            ->whereDate('started_date', '<=', $this->range['end'])
            ->whereDate('started_date', '<=', $this->todayDate())
            ->whereNull('finished_date')
            ->whereNull('canceled_date')
            ->count();
    }

    /**
     * Unfinished projects whose mandated date has passed.
     */
    #[Computed]
    public function overdueProjects(): int
    {
        return $this->projectQuery()
            ->whereNotNull('mandated_date')
            ->whereDate('mandated_date', '>=', $this->range['start'])
            ->whereDate('mandated_date', '<=', $this->range['end'])
            ->whereDate('mandated_date', '<', $this->todayDate())
            ->whereNull('finished_date')
            ->whereNull('canceled_date')
            ->count();
    }

    /**
     * Unfinished projects whose mandated date falls in the next seven days.
     */
    #[Computed]
    public function dueThisWeek(): int
    {
        return $this->projectQuery()
            ->whereNotNull('mandated_date')
            ->whereDate('mandated_date', '>=', $this->todayDate())
            ->whereDate('mandated_date', '<=', $this->todayDate(7))
            ->whereNull('finished_date')
            ->whereNull('canceled_date')
            ->count();
    }

    /**
     * Projects due within a week or already overdue.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projectsNeedingAttention(): Collection
    {
        return $this->projectQuery()
            ->whereNotNull('mandated_date')
            ->whereDate('mandated_date', '<=', $this->todayDate(7))
            ->whereNull('finished_date')
            ->whereNull('canceled_date')
            ->with('customer')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as finished_tasks_count' => fn (Builder $query): Builder => $query->whereNotNull('finished_at'),
            ])
            ->orderBy('mandated_date')
            ->limit(5)
            ->get();
    }

    /**
     * Open tasks assigned to the signed-in person.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function myTasks(): Collection
    {
        $person = Auth::user()?->person;

        if (! $person instanceof Person) {
            return new Collection;
        }

        return $this->openTaskQuery($person)
            ->with('project')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        $timezone = $this->userTimezone();

        /** @disregard P1013 */
        return view('livewire.dashboard', [
            'organization' => $this->currentOrganization(),
            'today' => CarbonImmutable::now($timezone)->toDateString(),
            'timezone' => $timezone,
        ])->title(__('Home'));
    }

    /**
     * @return Builder<Project>
     */
    private function projectQuery(): Builder
    {
        return Project::query()->where('owner_organization_id', $this->requireOrganization()->getKey());
    }

    /**
     * @return Builder<Task>
     */
    private function openTaskQuery(Person $person): Builder
    {
        return Task::query()
            ->where('assignee_id', $person->getKey())
            ->whereNull('finished_at')
            ->whereNull('canceled_at')
            ->whereHas('project', fn (Builder $query): Builder => $query
                ->where('owner_organization_id', $this->requireOrganization()->getKey())
                ->whereBetween('mandated_date', [$this->range['start'], $this->range['end']])
                ->whereNull('finished_date')
                ->whereNull('canceled_date'));
    }

    /**
     * @return MorphToMany<Person, Organization, OrganizationRelationship>
     */
    private function membershipQuery(): MorphToMany
    {
        return $this->requireOrganization()
            ->members()
            ->whereDate('organization_relationships.start_date', '>=', $this->range['start'])
            ->whereDate('organization_relationships.start_date', '<=', $this->range['end'])
            ->where(fn ($query) => $query
                ->whereNull('organization_relationships.finish_date')
                ->orWhereDate('organization_relationships.finish_date', '>=', $this->todayDate()));
    }

    /**
     * @return Builder<OrganizationRelationship>
     */
    private function contactQuery(): Builder
    {
        return OrganizationRelationship::query()
            ->forOrganization($this->requireOrganization())
            ->external()
            ->whereDate('start_date', '>=', $this->range['start'])
            ->whereDate('start_date', '<=', $this->range['end']);
    }

    /**
     * @return Builder<OrganizationRelationship>
     */
    private function expiringQuery(): Builder
    {
        return OrganizationRelationship::query()
            ->forOrganization($this->requireOrganization())
            ->external()
            ->notExpired()
            ->whereDate('finish_date', '>=', $this->range['start'])
            ->whereDate('finish_date', '<=', $this->range['end'])
            ->where(fn ($query) => $query
                ->whereNull('start_date')
                ->orWhereDate('start_date', '<=', $this->todayDate()));
    }

    /**
     * @return array{start: string, end: string}
     */
    private function defaultRange(): array
    {
        $today = CarbonImmutable::now($this->userTimezone());

        return [
            'start' => $today->startOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
            'end' => $today->endOfWeek(CarbonImmutable::SATURDAY)->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $range
     * @return array{start: string, end: string}
     */
    private function normalizeRange(array $range): array
    {
        $start = $this->dateKey($range['start'] ?? null);
        $end = $this->dateKey($range['end'] ?? null);

        if ($start === null || $end === null) {
            return $this->defaultRange();
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function dateKey(mixed $value): ?string
    {
        if (! \is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    private function todayDate(int $addDays = 0): string
    {
        return CarbonImmutable::now($this->userTimezone())->addDays($addDays)->toDateString();
    }

    private function userTimezone(): string
    {
        $user = Auth::user();

        return $user instanceof User && $user->timezone !== null ? $user->timezone : 'UTC';
    }
}
