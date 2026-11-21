<?php

declare(strict_types=1);

use App\Actions\Approvals\Audit\ApprovalPayload;
use App\Actions\Audit\LegacyPayload;
use App\Actions\Contacts\Audit\ContactRelationPayload;
use App\Actions\Memberships\Audit\MemberRoleChangedPayload;
use App\Actions\Projects\Audit\CancellationPayload;
use App\Actions\Projects\Audit\TaskChangedPayload;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the audit payload migration wraps legacy rows and unwraps them on rollback', function () {
    $legacy = [
        'contact.created' => ['kind' => 'individual', 'type' => 'customer'],
        'member.role_changed' => ['from' => 2, 'to' => 1],
        'approval.requested' => ['type' => 'membership_leave'],
        'task.changed' => ['action' => 'assigned_member', 'from' => 'Fery', 'to' => 'Narji'],
        'project.canceled' => ['action' => 'canceled', 'reason' => 'No longer needed'],
        'organization.deleted' => ['legacy' => true],
    ];

    foreach ($legacy as $event => $payload) {
        DB::table('audit_logs')->insert([
            'event' => $event,
            'changes' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    $migration = require database_path('migrations/2026_11_21_051654_wrap_audit_log_changes_with_payload_class.php');

    $migration->up();

    $classes = [
        'contact.created' => ContactRelationPayload::class,
        'member.role_changed' => MemberRoleChangedPayload::class,
        'approval.requested' => ApprovalPayload::class,
        'task.changed' => TaskChangedPayload::class,
        'project.canceled' => CancellationPayload::class,
    ];

    foreach ($classes as $event => $class) {
        expect(json_decode((string) DB::table('audit_logs')->where('event', $event)->value('changes'), true))
            ->toBe([$class, $legacy[$event]]);
    }

    expect(json_decode((string) DB::table('audit_logs')->where('event', 'organization.deleted')->value('changes'), true))
        ->toBe($legacy['organization.deleted']);

    $unmapped = AuditLog::query()->where('event', AuditEvent::OrganizationDeleted)->sole();

    expect($unmapped->changes)->toBeInstanceOf(LegacyPayload::class)
        ->and($unmapped->changes?->toArray())->toBe($legacy['organization.deleted'])
        ->and((string) $unmapped->changes)->toBe(__('Organization deleted'));

    $migration->down();

    foreach ($legacy as $event => $payload) {
        expect(json_decode((string) DB::table('audit_logs')->where('event', $event)->value('changes'), true))
            ->toBe($payload);
    }
});
