<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Events\Projects\ProjectCanceled;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelProject
{
    /**
     * Cancel a project with a mandatory reason.
     */
    public function handle(Project $project, string $reason, ?User $actor = null): Project
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('A cancellation reason is required.')]);
        }

        return DB::transaction(function () use ($project, $reason, $actor): Project {
            $project->update([
                'canceled_date' => today(),
                'remark' => $reason,
            ]);

            event(new ProjectCanceled(
                $project->ownerOrganization,
                $actor,
                $project,
                ['action' => 'canceled', 'reason' => $reason],
            ));

            return $project;
        });
    }
}
