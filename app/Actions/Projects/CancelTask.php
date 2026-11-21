<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Events\Projects\ProjectCanceled;
use App\Events\Projects\TaskCanceled;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelTask
{
    /**
     * Cancel a top-level task, disabling the tasks below it and cancelling the project.
     */
    public function handle(Task $task, string $reason, ?User $actor = null): Task
    {
        if ($task->isSubTask()) {
            throw ValidationException::withMessages(['reason' => __('Sub-tasks cannot be canceled.')]);
        }

        if ($task->isDisabled() || $task->status->isCompleted()) {
            throw ValidationException::withMessages(['reason' => __('This task cannot be canceled.')]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('A cancellation reason is required.')]);
        }

        return DB::transaction(function () use ($task, $reason, $actor): Task {
            $now = CarbonImmutable::now($actor->timezone ?? 'UTC');

            $task->update([
                'canceled_at' => $now,
                'remark' => $reason,
            ]);

            $project = $task->project;

            $project->update([
                'canceled_date' => $now->toDateString(),
                'remark' => $reason,
            ]);

            event(new TaskCanceled(
                $project->ownerOrganization,
                $actor,
                $task,
                $reason,
            ));

            event(new ProjectCanceled(
                $project->ownerOrganization,
                $actor,
                $project,
                $reason,
            ));

            return $task;
        });
    }
}
