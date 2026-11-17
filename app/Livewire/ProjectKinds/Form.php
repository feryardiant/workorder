<?php

declare(strict_types=1);

namespace App\Livewire\ProjectKinds;

use App\Events\Projects\ProjectKindChanged;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
     * @var list<array{title: string, summary: string, contact_id: ?int, contact: ?Organization, subtasks: list<array{title: string, summary: string, contact_id: ?int, contact: ?Organization}>}>
     */
    public array $tasks = [];

    public function mount(?string $kind = null): void
    {
        if ($kind === null) {
            return;
        }

        $model = ProjectKind::query()
            ->where('owner_organization_id', $this->requireOrganization()->getKey())
            ->with('tasks.contact', 'tasks.children.contact')
            ->findOrFail((int) $kind);

        $this->kindId = $model->getKey();
        $this->title = $model->title;
        $this->summary = (string) $model->summary;

        $this->tasks = array_values($model->tasks
            ->map(fn (ProjectKindTask $task): array => [
                'title' => $task->title,
                'summary' => (string) $task->summary,
                'contact_id' => $task->contact_id,
                'contact' => $task->contact,
                'subtasks' => array_values($task->children
                    ->map(fn (ProjectKindTask $child): array => [
                        'title' => $child->title,
                        'summary' => (string) $child->summary,
                        'contact_id' => $child->contact_id,
                        'contact' => $child->contact,
                    ])
                    ->all()),
            ])
            ->all());
    }

    public function addTask(): void
    {
        $this->dispatch('open-kind-task', index: null, task: []);
    }

    public function editTask(int $index): void
    {
        $task = $this->tasks[$index] ?? null;

        abort_if($task === null, 404);

        $this->dispatch('open-kind-task', index: $index, task: $task);
    }

    /**
     * @param  array{title: string, summary: string, contact_id: ?int, subtasks: list<array{title: string, summary: string, contact_id: ?int}>}  $task
     */
    #[On('kind-task-saved')]
    public function applyTask(?int $index, array $task): void
    {
        $contactId = $task['contact_id'];
        $subtasks = [];

        foreach ($task['subtasks'] as $subtask) {
            $subtaskContactId = $subtask['contact_id'];

            $subtasks[] = [
                'title' => $subtask['title'],
                'summary' => $subtask['summary'],
                'contact_id' => $subtaskContactId,
                'contact' => $subtaskContactId === null ? null : Organization::query()->find($subtaskContactId),
            ];
        }

        $value = [
            'title' => $task['title'],
            'summary' => $task['summary'],
            'contact_id' => $contactId,
            'contact' => $contactId === null ? null : Organization::query()->find($contactId),
            'subtasks' => $subtasks,
        ];

        if ($index === null) {
            $this->tasks[] = $value;
        } else {
            $this->tasks[$index] = $value;
        }
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
                    'contact_id' => $task['contact_id'] ?? null,
                ]);

                foreach ($task['subtasks'] as $childIndex => $subtask) {
                    $kind->allTasks()->create([
                        'parent_id' => $parent->getKey(),
                        'position' => $childIndex + 1,
                        'title' => $subtask['title'],
                        'summary' => $subtask['summary'] === '' ? null : $subtask['summary'],
                        'contact_id' => $subtask['contact_id'] ?? null,
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
