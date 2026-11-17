<?php

declare(strict_types=1);

namespace App\Livewire\ProjectKinds;

use App\Events\Projects\ProjectKindChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Create or edit a project kind and its two-level task tree.
 *
 * @property-read Collection<int, AuditLog> $timeline
 */
class Form extends Component
{
    use HasCurrentOrganization;

    public ?int $kindId = null;

    public string $title = '';

    public string $summary = '';

    /**
     * @var list<array{title: string, summary: string, subtasks: list<array{title: string, summary: string}>}>
     */
    public array $tasks = [];

    public bool $showTaskForm = false;

    public ?int $editingTask = null;

    public string $taskTitle = '';

    public string $taskSummary = '';

    public bool $showSubtaskForm = false;

    public ?int $subtaskParent = null;

    public ?int $editingSubtask = null;

    public string $subtaskTitle = '';

    public string $subtaskSummary = '';

    public function mount(?string $kind = null): void
    {
        if ($kind === null) {
            return;
        }

        $model = ProjectKind::query()
            ->where('owner_organization_id', $this->requireOrganization()->getKey())
            ->with('tasks.children')
            ->findOrFail((int) $kind);

        $this->kindId = $model->getKey();
        $this->title = $model->title;
        $this->summary = (string) $model->summary;

        $this->tasks = array_values($model->tasks
            ->map(fn (ProjectKindTask $task): array => [
                'title' => $task->title,
                'summary' => (string) $task->summary,
                'subtasks' => array_values($task->children
                    ->map(fn (ProjectKindTask $child): array => [
                        'title' => $child->title,
                        'summary' => (string) $child->summary,
                    ])
                    ->all()),
            ])
            ->all());
    }

    public function addTask(): void
    {
        $this->editingTask = null;
        $this->taskTitle = '';
        $this->taskSummary = '';
        $this->showTaskForm = true;
    }

    public function editTask(int $index): void
    {
        $task = $this->tasks[$index] ?? null;

        abort_if($task === null, 404);

        $this->editingTask = $index;
        $this->taskTitle = $task['title'];
        $this->taskSummary = $task['summary'];
        $this->showTaskForm = true;
    }

    public function saveTask(): void
    {
        $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskSummary' => ['nullable', 'string', 'max:5000'],
        ]);

        $task = [
            'title' => $this->taskTitle,
            'summary' => $this->taskSummary,
            'subtasks' => $this->editingTask === null ? [] : ($this->tasks[$this->editingTask]['subtasks'] ?? []),
        ];

        if ($this->editingTask === null) {
            $this->tasks[] = $task;
        } else {
            $this->tasks[$this->editingTask] = $task;
        }

        $this->reset('showTaskForm', 'editingTask', 'taskTitle', 'taskSummary');
    }

    public function deleteTask(int $index): void
    {
        array_splice($this->tasks, $index, 1);
    }

    public function moveTask(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->tasks[$target])) {
            return;
        }

        [$this->tasks[$index], $this->tasks[$target]] = [$this->tasks[$target], $this->tasks[$index]];
    }

    public function addSubtask(int $parent): void
    {
        $this->subtaskParent = $parent;
        $this->editingSubtask = null;
        $this->subtaskTitle = '';
        $this->subtaskSummary = '';
        $this->showSubtaskForm = true;
    }

    public function editSubtask(int $parent, int $index): void
    {
        $subtask = $this->tasks[$parent]['subtasks'][$index] ?? null;

        abort_if($subtask === null, 404);

        $this->subtaskParent = $parent;
        $this->editingSubtask = $index;
        $this->subtaskTitle = $subtask['title'];
        $this->subtaskSummary = $subtask['summary'];
        $this->showSubtaskForm = true;
    }

    public function saveSubtask(): void
    {
        $this->validate([
            'subtaskTitle' => ['required', 'string', 'max:255'],
            'subtaskSummary' => ['nullable', 'string', 'max:5000'],
        ]);

        $parent = (int) $this->subtaskParent;
        $subtask = ['title' => $this->subtaskTitle, 'summary' => $this->subtaskSummary];

        if ($this->editingSubtask === null) {
            $this->tasks[$parent]['subtasks'][] = $subtask;
        } else {
            $this->tasks[$parent]['subtasks'][$this->editingSubtask] = $subtask;
        }

        $this->reset('showSubtaskForm', 'subtaskParent', 'editingSubtask', 'subtaskTitle', 'subtaskSummary');
    }

    public function deleteSubtask(int $parent, int $index): void
    {
        array_splice($this->tasks[$parent]['subtasks'], $index, 1);
    }

    public function moveSubtask(int $parent, int $index, int $direction): void
    {
        $target = $index + $direction;
        $subtasks = $this->tasks[$parent]['subtasks'];

        if (! isset($subtasks[$target])) {
            return;
        }

        [$subtasks[$index], $subtasks[$target]] = [$subtasks[$target], $subtasks[$index]];
        $this->tasks[$parent]['subtasks'] = $subtasks;
    }

    public function save(): void
    {
        $this->authorizeManager();

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($this->tasks as $task) {
            if (trim($task['title']) === '') {
                $this->addError('tasks', __('Every task needs a title.'));

                return;
            }
        }

        $kind = DB::transaction(function (): ProjectKind {
            $kind = $this->kindId === null
                ? new ProjectKind(['owner_organization_id' => $this->requireOrganization()->getKey()])
                : ProjectKind::query()
                    ->where('owner_organization_id', $this->requireOrganization()->getKey())
                    ->findOrFail($this->kindId);

            $kind->fill(['title' => $this->title, 'summary' => $this->summary === '' ? null : $this->summary]);
            $kind->save();

            $kind->allTasks()->delete();

            foreach ($this->tasks as $index => $task) {
                $parent = $kind->allTasks()->create([
                    'parent_id' => null,
                    'position' => $index + 1,
                    'title' => $task['title'],
                    'summary' => $task['summary'] === '' ? null : $task['summary'],
                ]);

                foreach ($task['subtasks'] as $childIndex => $subtask) {
                    $kind->allTasks()->create([
                        'parent_id' => $parent->getKey(),
                        'position' => $childIndex + 1,
                        'title' => $subtask['title'],
                        'summary' => $subtask['summary'] === '' ? null : $subtask['summary'],
                    ]);
                }
            }

            return $kind;
        });

        Flux::toast(variant: 'success', text: __('Project kind saved.'));

        event(new ProjectKindChanged($this->currentOrganization(), Auth::user(), $kind, [
            'action' => 'saved',
            'title' => $kind->title,
        ]));

        $this->redirectRoute('project-kinds.edit', ['kind' => $kind->getKey()], navigate: true);
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.project-kinds.form', [
            'timeline' => $this->timeline,
        ])->title(
            $this->kindId === null ? __('Create new project kind') : __('Edit: :title', ['title' => $this->title]),
        );
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function timeline(): Collection
    {
        if ($this->kindId === null) {
            return new Collection;
        }

        return AuditLog::query()
            ->where('subject_type', (new ProjectKind)->getMorphClass())
            ->where('subject_id', $this->kindId)
            ->with('actor')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();
    }
}
