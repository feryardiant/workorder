<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Actions\Projects\Audit\TaskChangedPayload;
use App\Enums\AuditEvent;
use App\Enums\TaskAction;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;

final class TaskChanged extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Task $task,
        TaskAction $action,
        ?string $from = null,
        ?string $to = null,
    ) {
        parent::__construct($organization, $actor, $task, new TaskChangedPayload($action, $from, $to));
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::TaskChanged;
    }
}
