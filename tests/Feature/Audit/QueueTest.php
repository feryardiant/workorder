<?php

declare(strict_types=1);

use App\Actions\Audit\LegacyPayload;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Events\Contacts\ContactCreated;
use App\Listeners\RecordAuditLog;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('audit events are queued for the audit listener', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Owner);

    event(new ContactCreated($organization, $actor, $organization, OrganizationRelationshipType::Customer));

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === RecordAuditLog::class);
});

test('late audit events are skipped after the organization is deleted', function () {
    $organization = Organization::factory()->create();

    $event = new ContactCreated($organization, null, $organization, OrganizationRelationshipType::Customer);

    $organization->forceDelete();

    app(RecordAuditLog::class)->handle($event);

    expect(AuditLog::query()->where('organization_id', $organization->getKey())->count())->toBe(0);
});

test('audit rows drop an actor that was deleted before the listener runs', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->withProfile()->create();

    $event = new ContactCreated($organization, $actor, $organization, OrganizationRelationshipType::Customer);

    $actor->delete();

    app(RecordAuditLog::class)->handle($event);

    $log = AuditLog::query()->where('organization_id', $organization->getKey())->sole();

    expect($log->actor_id)->toBeNull();
});

test('audit rows keep the event occurrence time instead of the queue time', function () {
    /** @var TestCase $this */
    $organization = Organization::factory()->create();

    $this->travelTo('2026-11-15 23:59:00');

    $event = new ContactCreated($organization, null, $organization, OrganizationRelationshipType::Customer);

    $this->travelTo('2026-11-16 00:05:00');

    app(RecordAuditLog::class)->handle($event);

    expect(AuditLog::query()
        ->where('organization_id', $organization->getKey())
        ->sole()
        ->created_at
        ->toDateTimeString())->toBe('2026-11-15 23:59:00');
});

test('audit events queued before the payload refactor keep their legacy changes', function () {
    $organization = Organization::factory()->create();

    $event = (new ReflectionClass(ContactCreated::class))->newInstanceWithoutConstructor();
    $event->organizationId = $organization->getKey();
    $event->actorId = null;
    $event->subjectType = null;
    $event->subjectId = null;
    $event->relationshipPeriodId = null;
    $event->occurredAt = now()->toImmutable();
    $event->changes = ['type' => 'customer'];

    app(RecordAuditLog::class)->handle($event);

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditEvent::ContactCreated)
        ->and($log->changes)->toBeInstanceOf(LegacyPayload::class)
        ->and($log->changes?->toArray())->toBe(['type' => 'customer'])
        ->and((string) $log->changes)->toBe(__('Contact created'));
});

test('audit events without a payload or legacy changes are skipped', function () {
    $event = (new ReflectionClass(ContactCreated::class))->newInstanceWithoutConstructor();

    app(RecordAuditLog::class)->handle($event);

    expect(AuditLog::query()->count())->toBe(0);
});
