<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Actions\Projects\Audit\ProjectKindChangedPayload;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\ProjectKind;
use App\Models\User;

final class ProjectKindChanged extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        ProjectKind $kind,
        string $title,
    ) {
        parent::__construct($organization, $actor, $kind, new ProjectKindChangedPayload($title));
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ProjectKindChanged;
    }
}
