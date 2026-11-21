<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Actions\Projects\Audit\ProjectChangedPayload;
use App\Enums\AuditEvent;
use App\Enums\ProjectAction;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

final class ProjectChanged extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Project $project,
        ProjectAction $action,
        ?string $title = null,
    ) {
        parent::__construct($organization, $actor, $project, new ProjectChangedPayload($action, $title));
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ProjectChanged;
    }
}
