<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Events\Projects\ProjectChanged;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectKind;
use App\Models\ProjectKindTask;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProject
{
    /**
     * Create a project and copy its kind's task tree.
     *
     * @param  array{title?: ?string, summary?: ?string, remark?: ?string, settings?: array<string, mixed>|null, mandated_date?: ?string, started_date?: ?string}  $data
     */
    public function handle(
        Organization $tenant,
        ProjectKind $kind,
        Organization $customer,
        ?Person $assignee = null,
        array $data = [],
        ?User $actor = null,
    ): Project {
        if ($kind->owner_organization_id !== $tenant->getKey()) {
            throw new \InvalidArgumentException(__('The project kind does not belong to this organization.'));
        }

        return DB::transaction(function () use ($tenant, $kind, $customer, $assignee, $data, $actor): Project {
            // Serialize the daily sequence per kind.
            ProjectKind::query()->whereKey($kind->getKey())->lockForUpdate()->firstOrFail();

            $now = CarbonImmutable::now($actor->timezone ?? 'UTC');

            $code = $this->nextCode($kind, $now);

            $project = Project::create([
                'uuid' => (string) Str::uuid7(),
                'owner_organization_id' => $tenant->getKey(),
                'kind_id' => $kind->getKey(),
                'customer_id' => $customer->getKey(),
                'assignee_id' => $assignee?->getKey(),
                'code' => $code,
                'slug' => Str::slug($code),
                'title' => $data['title'] ?? \sprintf(
                    '%s for %s (%s)',
                    $kind->title,
                    $customer->name,
                    $now->format('d-m-Y'),
                ),
                'summary' => $data['summary'] ?? null,
                'remark' => $data['remark'] ?? null,
                'settings' => $data['settings'] ?? null,
                'mandated_date' => $data['mandated_date'] ?? null,
                'started_date' => $data['started_date'] ?? null,
            ]);

            $this->copyTaskTree($project, $kind);

            event(new ProjectChanged($tenant, $actor, $project, ['action' => 'created', 'title' => $project->title]));

            return $project;
        });
    }

    /**
     * Build the next daily code for the kind.
     */
    private function nextCode(ProjectKind $kind, CarbonImmutable $now): string
    {
        $prefix = \sprintf('%s - %s-', $kind->title, $now->format('Ymd'));

        $last = Project::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $sequence = $last === null ? 1 : ((int) Str::afterLast((string) $last, '-')) + 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Copy the kind's tasks and sub-tasks in order.
     */
    private function copyTaskTree(Project $project, ProjectKind $kind): void
    {
        $tasks = $kind->tasks()->with('children')->get();

        foreach ($tasks as $kindTask) {
            $task = $this->copyTask($project, $kindTask);

            foreach ($kindTask->children as $kindSubTask) {
                $this->copyTask($project, $kindSubTask, $task);
            }
        }
    }

    /**
     * Copy one kind task into the project.
     */
    private function copyTask(Project $project, ProjectKindTask $kindTask, ?Task $parent = null): Task
    {
        return Task::create([
            'project_id' => $project->getKey(),
            'parent_id' => $parent?->getKey(),
            'assignee_id' => $project->assignee_id,
            'contact_id' => $kindTask->contact_id,
            'slug' => Str::slug(\sprintf(
                '%s-%d-%s-%d-%d',
                $project->slug,
                $project->getKey(),
                $kindTask->title,
                $parent === null ? $kindTask->position : $parent->order,
                $kindTask->position,
            )),
            'title' => $kindTask->title,
            'summary' => $kindTask->summary,
            'order' => $kindTask->position,
        ]);
    }
}
