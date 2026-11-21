<?php

declare(strict_types=1);

namespace App\Events\Projects;

use App\Actions\Projects\Audit\AttachmentChangedPayload;
use App\Enums\AttachmentAction;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AttachmentChanged extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Model $attachable,
        AttachmentAction $action,
        int $count,
        ?string $name = null,
    ) {
        parent::__construct(
            $organization,
            $actor,
            $attachable,
            new AttachmentChangedPayload($action, $count, $name),
        );
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::AttachmentChanged;
    }
}
