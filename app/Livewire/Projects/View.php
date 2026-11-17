<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Actions\FileStorage;
use App\Actions\Projects\CancelProject;
use App\Actions\Projects\CancelTask;
use App\Enums\OrganizationRelationshipType;
use App\Enums\ProjectStatus;
use App\Events\Projects\ProjectChanged;
use App\Events\Projects\TaskChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Person> $assignees
 * @property-read Collection<int, Person> $members
 * @property-read Collection<int, Organization> $contacts
 * @property-read Collection<int, AuditLog> $timeline
 * @property-read bool $canFinish
 */
class View extends Component
{
    use HasCurrentOrganization, WithFileUploads;

    #[Locked]
    public Project $project;

    public bool $showEdit = false;

    public string $title = '';

    public string $summary = '';

    public string $remark = '';

    public ?string $mandatedDate = null;

    public ?int $assigneeId = null;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $uploads = [];

    public bool $showTaskInfo = false;

    public ?int $taskInfoId = null;

    public string $taskTitle = '';

    public string $taskSummary = '';

    public bool $showMember = false;

    public ?int $memberTaskId = null;

    public ?int $memberId = null;

    public bool $showContact = false;

    public ?int $contactTaskId = null;

    public ?int $contactId = null;

    public bool $showCancel = false;

    public ?int $cancelTaskId = null;

    public string $cancelReason = '';

    public bool $showCancelProject = false;

    public string $projectCancelReason = '';

    public bool $showArchive = false;

    public function mount(Project $project): void
    {
        $organization = $this->requireOrganization();

        abort_unless($project->owner_organization_id === $organization->getKey(), 404);

        $this->project = $project->load(['kind', 'customer', 'assignee', 'attachments']);

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
    public function contacts(): Collection
    {
        return $this->requireOrganization()->organizationCounterparties(OrganizationRelationshipType::Vendor)->get();
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
            ->with('actor')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function openEdit(): void
    {
        $this->fillEditForm();
        $this->showEdit = true;
    }

    public function saveProject(FileStorage $fileStorage): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'mandatedDate' => ['nullable', 'date'],
            'assigneeId' => ['nullable', 'integer'],
            'uploads.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp'],
        ]);

        $assignee = $this->assigneeId === null ? null : $this->members->firstWhere('id', $this->assigneeId);

        $this->project->update([
            'title' => $this->title,
            'summary' => $this->summary === '' ? null : $this->summary,
            'remark' => $this->remark === '' ? null : $this->remark,
            'mandated_date' => $this->mandatedDate,
            'assignee_id' => $assignee?->getKey(),
        ]);

        foreach ($this->uploads as $upload) {
            $path = $fileStorage->handle($upload, 'attachments');

            $this->project->attachments()->create([
                'title' => $upload->getClientOriginalName(),
                'name' => $upload->getClientOriginalName(),
                'path' => $path,
                'mime' => $upload->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $upload->getSize(),
            ]);
        }

        $this->reset('uploads', 'showEdit');
        unset($this->assignees);

        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ['action' => 'updated']));

        Flux::toast(variant: 'success', text: __('Project updated.'));
    }

    public function removeAttachment(int $attachmentId, FileStorage $fileStorage): void
    {
        $attachment = $this->project->attachments()->findOrFail($attachmentId);

        $fileStorage->delete($attachment->path);

        $attachment->delete();
    }

    public function openTaskInfo(int $taskId): void
    {
        $task = $this->findTask($taskId);

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

        $task->update([
            'title' => $this->taskTitle,
            'summary' => $this->taskSummary === '' ? null : $this->taskSummary,
        ]);

        $this->reset('showTaskInfo', 'taskInfoId', 'taskTitle', 'taskSummary');
        unset($this->tasks);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, ['action' => 'updated']));

        Flux::toast(variant: 'success', text: __('Task updated.'));
    }

    public function startTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->isCanceled(), 403);

        $task->update(['started_at' => now()]);

        unset($this->tasks);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, ['action' => 'started']));
    }

    public function toggleSubTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_unless($task->isSubTask(), 404);
        abort_if($task->isDisabled() || $task->isCanceled(), 403);

        $finished = $task->finished_at === null;

        $task->update([
            'finished_at' => $finished ? now() : null,
        ]);

        unset($this->tasks);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, [
            'action' => $finished ? 'finished' : 'reopened',
        ]));
    }

    public function openMember(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->isCanceled(), 403);

        $this->memberTaskId = $task->getKey();
        $this->memberId = $task->assignee_id;
        $this->showMember = true;
    }

    public function assignMember(): void
    {
        $task = $this->findTask((int) $this->memberTaskId);

        $member = $this->members->firstWhere('id', $this->memberId);

        abort_if($member === null, 422);

        $task->update(['assignee_id' => $member->getKey()]);

        $this->reset('showMember', 'memberTaskId', 'memberId');
        unset($this->tasks, $this->assignees);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, [
            'action' => 'assigned_member',
            'assignee' => $member->name,
        ]));
    }

    public function openContact(int $taskId): void
    {
        $task = $this->findTask($taskId);

        abort_if($task->isDisabled() || $task->isCanceled(), 403);

        $this->contactTaskId = $task->getKey();
        $this->contactId = $task->contact_id;
        $this->showContact = true;
    }

    public function assignContact(): void
    {
        $task = $this->findTask((int) $this->contactTaskId);

        $contact = $this->contacts->firstWhere('id', $this->contactId);

        abort_if($contact === null, 422);

        $task->update(['contact_id' => $contact->getKey()]);

        $this->reset('showContact', 'contactTaskId', 'contactId');
        unset($this->tasks);

        event(new TaskChanged($this->project->ownerOrganization, Auth::user(), $task, [
            'action' => 'assigned_contact',
            'contact' => $contact->name,
        ]));
    }

    public function openCancel(int $taskId): void
    {
        abort_if($this->findTask($taskId)->isSubTask(), 403);

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
        unset($this->tasks, $this->timeline);

        Flux::toast(variant: 'success', text: __('Task canceled.'));
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
        unset($this->tasks, $this->timeline);

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

        $this->project->update(['finished_date' => today()]);

        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ['action' => 'finished']));

        $this->project->refresh();

        unset($this->tasks, $this->canFinish, $this->timeline);

        Flux::toast(variant: 'success', text: __('Project finished.'));
    }

    public function confirmArchive(): void
    {
        $this->showArchive = true;
    }

    #[On('contact-updated')]
    public function refreshContacts(?int $contactId = null): void
    {
        unset($this->contacts);

        if ($contactId !== null) {
            $this->contactId = $contactId;
        }
    }

    public function archive(): void
    {
        event(new ProjectChanged($this->project->ownerOrganization, Auth::user(), $this->project, ['action' => 'archived']));

        $this->project->delete();

        $this->redirectRoute('projects.index', navigate: true);
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.projects.view', [
            'tasks' => $this->tasks,
            'assignees' => $this->assignees,
            'members' => $this->members,
            'contacts' => $this->contacts,
            'timeline' => $this->timeline,
        ])->title($this->project->title);
    }

    private function fillEditForm(): void
    {
        $this->title = $this->project->title;
        $this->summary = (string) $this->project->summary;
        $this->remark = (string) $this->project->remark;
        $this->mandatedDate = $this->project->mandated_date?->toDateString();
        $this->assigneeId = $this->project->assignee_id;
    }

    /**
     * Resolve a task that belongs to the project.
     */
    private function findTask(int $taskId): Task
    {
        return $this->project->allTasks()->with('parent')->findOrFail($taskId);
    }
}
