<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Audit\LegacyPayload;
use App\Events\AuditableEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

final class RecordAuditLog implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $backoff = 10;

    public function handle(AuditableEvent $event): void
    {
        $payload = isset($event->payload)
            ? $event->payload
            : ($event->changes === [] ? null : new LegacyPayload($event->changes));

        if ($payload === null) {
            Log::warning('Skipping an audit event without a payload.', [
                'event' => $event::class,
            ]);

            return;
        }

        if ($event->organizationId !== null) {
            $organization = Organization::withTrashed()->find($event->organizationId);

            if ($organization === null || $organization->trashed()) {
                return;
            }
        }

        AuditLog::create([
            'organization_id' => $event->organizationId,
            'actor_id' => $event->actorId !== null && User::query()->whereKey($event->actorId)->exists()
                ? $event->actorId
                : null,
            'event' => $event->auditEvent(),
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'organization_relationship_period_id' => $event->relationshipPeriodId,
            'changes' => $payload,
            'created_at' => $event->occurredAt,
        ]);
    }
}
