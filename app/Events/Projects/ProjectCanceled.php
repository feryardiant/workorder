<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Actions\Projects\Audit\CancellationPayload;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

final class ProjectCanceled extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Project $project,
        string $reason,
    ) {
        parent::__construct($organization, $actor, $project, new CancellationPayload($reason));
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ProjectCanceled;
    }
}
