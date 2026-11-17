<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;

final class TaskCanceled extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::TaskCanceled;
    }
}
