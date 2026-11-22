<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Actions\Projects\CancelProject;
use App\Actions\Projects\CancelTask;
use App\Enums\OrganizationRelationshipType;
use App\Enums\ProjectAction;
use App\Enums\ProjectStatus;
use App\Enums\TaskAction;
use App\Events\Projects\ProjectChanged;
use App\Events\Projects\TaskChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Person> $assignees
 * @property-read Collection<int, Person> $members
 * @property-read bool $isManager
 * @property-read Collection<int, Organization> $customers
 * @property-read Collection<int, Attachment> $projectAttachments
 * @property-read bool $canFinish
 * @property-read bool $canManage
 */
class View extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public Project $project;

    public bool $showEdit = false;

    public string $title = '';

    public string $summary = '';

    public string $remark = '';

    public ?string $mandatedDate = null;

    public ?int $assigneeId = null;

    public ?int $customerId = null;

    public bool $showTaskInfo = false;

    public ?int $taskInfoId = null;

    public string $taskTitle = '';

    public string $taskSummary = '';

    public bool $showCancel = false;

    public ?int $cancelTaskId = null;

    public string $cancelReason = '';

    public bool $showPostpone = false;

    public ?int $postponeTaskId = null;

    public string $postponeReason = '';

    public bool $showCancelProject = false;

    public string $projectCancelReason = '';

    public bool $showArchive = false;

    public function mount(Project $project): void
    {
        $organization = $this->requireOrganization();

        abort_unless($project->owner_organization_id === $organization->getKey(), 404);

        $this->project = $project->load(['kind', 'customer', 'assignee']);

        $this->fillEditForm();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->project->tasks()
            ->with(['subTasks.assignee', 'assignee', 'contact'])
            ->withCount('attachments')
            ->get();
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function assignees(): Collection
    {
        $ids = collect([$this->project->assignee])
            ->concat($this->project->allTasks()->with('subTasks')->get()->flatMap(
                fn (Task $task): array => [$task->assignee, ...$task->subTasks->pluck('assignee')->all()],
            ))
            ->filter()
            ->unique('id')
            ->pluck('id')
            ->all();

        return Person::query()->whereIn('id', $ids)->get();
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->requireOrganization()->members()->get();
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function customers(): Collection
    {
        return $this->requireOrganization()
            ->organizationCounterparties(OrganizationRelationshipType::Customer)
            ->get();
    }

    /**
     * Every attachment of the project and its top-level tasks.
     *
     * @return Collection<int, Attachment>
     */
    #[Computed]
    public function projectAttachments(): Collection
    {
        $projectAttachments = $this->project->attachments()->get();

        $taskAttachments = Attachment::query()
            ->where('attachable_type', (new Task)->getMorphClass())
            ->whereIn('attachable_id', $this->project->allTasks()->pluck('id'))
            ->get();

        return new Collection(
            $projectAttachments
                ->concat($taskAttachments)
                ->sortByDesc(fn (Attachment $attachment): int => $attachment->created_at?->getTimestamp() ?? 0)
                ->values()
                ->all(),
        );
    }

    #[On('attachments-updated')]
    public function refreshAttachments(): void
    {
        unset($this->tasks, $this->projectAttachments);
    }

    public function openEdit(): void
    {
        abort_unless($this->canManage && ! $this->project->status->isCanceled(), 403);

        $this->fillEditForm();
        $this->showEdit = true;
    }

    public function saveProject(): void
    {
        abort_unless($this->canManage && ! $this->project->status->isCanceled(), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'mandatedDate' => ['nullable', 'date'],
            'assigneeId' => ['nullable', 'integer'],
            'customerId' => ['required', 'integer'],
        ]);

        $assignee = $this->assigneeId === null ? null : $this->members->firstWhere('id', $this->assigneeId);
        $customer = $this->customers->firstWhere('id', $this->customerId);

        abort_if($customer === null, 422);

        $this->project->update([
            'title' => $this->title,
            'customer_id' => $customer->getKey(),
            'summary' => $this->summary === '' ? null : $this->summary,
            'remark' => $this->remark === '' ? null : $this->remark,
            'mandated_date' => $this->mandatedDate,
            'assignee_id' => $assignee?->getKey(),
        ]);

        $this->reset('showEdit');
        unset($this->assignees);

        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ProjectAction::Updated));

        Flux::toast(variant: 'success', text: __('Project updated.'));
    }

    public function openTaskInfo(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->status->isCompleted(), 403);

        $this->taskInfoId = $task->getKey();
        $this->taskTitle = $task->title;
        $this->taskSummary = (string) $task->summary;
        $this->showTaskInfo = true;
    }

    public function saveTaskInfo(): void
    {
        $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskSummary' => ['nullable', 'string', 'max:5000'],
        ]);

        $task = $this->findTask((int) $this->taskInfoId);

        abort_if($task->isDisabled() || $task->status->isCompleted(), 403);

        $task->update([
            'title' => $this->taskTitle,
            'summary' => $this->taskSummary === '' ? null : $this->taskSummary,
        ]);

        $this->reset('showTaskInfo', 'taskInfoId', 'taskTitle', 'taskSummary');
        unset($this->tasks);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, TaskAction::Updated));

        Flux::toast(variant: 'success', text: __('Task updated.'));
    }

    public function startTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->isCanceled(), 403);
        abort_unless($task->status->isNotStarted(), 403);
        abort_if($task->isBlockedByPostponed(), 403);

        $task->update(['started_at' => $this->now(), 'postponed_at' => null]);

        if ($task->project->status->isNotStarted() || $task->project->status->isScheduled()) {
            $task->project->update(['started_date' => $this->todayDate()]);
            $this->project->refresh();
        }

        unset($this->tasks, $this->canFinish);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, TaskAction::Started));
    }

    public function finishTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->isCanceled(), 403);
        abort_unless($task->status->isOnGoing(), 403);

        $task->update(['finished_at' => $this->now()]);

        unset($this->tasks, $this->canFinish);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, TaskAction::Finished));

        Flux::toast(variant: 'success', text: __('Task finished.'));
    }

    public function toggleSubTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_unless($task->isSubTask(), 404);
        abort_if($task->isDisabled() || $task->isCanceled(), 403);
        abort_if($task->parent?->status->isFinished() === true, 403);

        $finished = $task->finished_at === null;

        if ($finished) {
            abort_if($task->isPostponed() || $task->isBlockedByPostponed(), 403);
        }

        $task->update([
            'finished_at' => $finished ? $this->now() : null,
        ]);

        $parent = $task->parent;

        if ($finished && $parent !== null && $parent->started_at === null) {
            $parent->update(['started_at' => $this->now()]);
        }

        unset($this->tasks);

        event(new TaskChanged(
            $this->project->ownerOrganization,
            Auth::user(),
            $task,
            $finished ? TaskAction::Finished : TaskAction::Reopened,
        ));
    }

    public function openCancel(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isSubTask() || $task->status->isCompleted(), 403);

        $this->cancelTaskId = $taskId;
        $this->cancelReason = '';
        $this->showCancel = true;
    }

    public function cancelTask(CancelTask $cancelTask): void
    {
        $task = $this->findTask((int) $this->cancelTaskId);

        $cancelTask->handle($task, $this->cancelReason, Auth::user());

        $this->project->refresh();

        $this->reset('showCancel', 'cancelTaskId', 'cancelReason');
        unset($this->tasks);

        Flux::toast(variant: 'success', text: __('Task canceled.'));
    }

    public function openPostpone(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_unless($task->canPostpone(), 403);

        $this->postponeTaskId = $task->getKey();
        $this->postponeReason = '';
        $this->showPostpone = true;
    }

    public function postponeTask(): void
    {
        $this->postponeReason = trim($this->postponeReason);

        $this->validate([
            'postponeReason' => ['required', 'string', 'max:5000'],
        ], [
            'postponeReason.required' => __('A postponement reason is required.'),
        ]);

        $task = $this->findTask((int) $this->postponeTaskId);

        abort_unless($task->canPostpone(), 403);

        $reason = $this->postponeReason;

        $task->update(['postponed_at' => $this->now()]);

        $this->reset('showPostpone', 'postponeTaskId', 'postponeReason');
        unset($this->tasks, $this->canFinish);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, TaskAction::Postponed, reason: $reason));

        Flux::toast(variant: 'success', text: __('Task postponed.'));
    }

    public function confirmCancelProject(): void
    {
        $this->projectCancelReason = '';
        $this->showCancelProject = true;
    }

    public function cancelProject(CancelProject $cancelProject): void
    {
        $cancelProject->handle($this->project, $this->projectCancelReason, Auth::user());

        $this->project->refresh();

        $this->reset('showCancelProject', 'projectCancelReason');
        unset($this->tasks);

        Flux::toast(variant: 'success', text: __('Project canceled.'));
    }

    /**
     * Determine whether the project can be finished.
     */
    #[Computed]
    public function canFinish(): bool
    {
        return $this->project->status !== ProjectStatus::Canceled
            && $this->project->finished_date === null
            && $this->tasks->filter(fn (Task $task): bool => $task->finished_at === null)->count() === 1;
    }

    public function finishProject(): void
    {
        abort_unless($this->canFinish, 403);

        $this->project->update(['finished_date' => $this->todayDate()]);

        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ProjectAction::Finished));

        $this->project->refresh();

        unset($this->tasks, $this->canFinish);

        Flux::toast(variant: 'success', text: __('Project finished.'));
    }

    public function confirmArchive(): void
    {
        $this->showArchive = true;
    }

    public function archive(): void
    {
        abort_unless($this->canManage, 403);

        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ProjectAction::Archived));

        $this->project->delete();

        $this->redirectRoute('projects.index', navigate: true);
    }

    #[On('task-updated')]
    public function refreshTask(): void
    {
        unset($this->tasks, $this->assignees, $this->canFinish);
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.projects.view', [
            'tasks' => $this->tasks,
            'assignees' => $this->assignees,
            'members' => $this->members,
            'customers' => $this->customers,
            'projectAttachments' => $this->projectAttachments,
        ])->title($this->project->title);
    }

    private function fillEditForm(): void
    {
        $this->customerId = $this->project->customer_id;
        $this->title = $this->project->title;
        $this->summary = (string) $this->project->summary;
        $this->remark = (string) $this->project->remark;
        $this->mandatedDate = $this->project->mandated_date?->toDateString();
        $this->assigneeId = $this->project->assignee_id;
    }

    /**
     * Resolve a task that belongs to the project.
     */
    #[Computed]
    public function canManage(): bool
    {
        return $this->isManager || $this->project->assignee_id === Auth::user()?->person?->getKey();
    }

    private function now(): CarbonImmutable
    {
        $user = Auth::user();

        return CarbonImmutable::now($user->timezone ?? 'UTC');
    }

    private function todayDate(): string
    {
        return $this->now()->toDateString();
    }

    private function findTask(int $taskId): Task
    {
        return $this->project->allTasks()->with('parent')->findOrFail($taskId);
    }
}
