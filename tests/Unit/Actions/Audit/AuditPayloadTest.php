<?php

declare(strict_types=1);

use App\Actions\Approvals\Audit\ApprovalPayload;
use App\Actions\Audit\AuditPayload;
use App\Actions\Audit\FeatureToggledPayload;
use App\Actions\Contacts\Audit\ContactDeletedPayload;
use App\Actions\Contacts\Audit\ContactRelationPayload;
use App\Actions\Contacts\Audit\ContactUpdatedPayload;
use App\Actions\Memberships\Audit\MemberAddedPayload;
use App\Actions\Memberships\Audit\MemberLeftPayload;
use App\Actions\Memberships\Audit\MemberRemovedPayload;
use App\Actions\Memberships\Audit\MemberRoleChangedPayload;
use App\Actions\Organizations\Audit\OrganizationCreatedPayload;
use App\Actions\Organizations\Audit\OrganizationUpdatedPayload;
use App\Actions\Projects\Audit\AttachmentChangedPayload;
use App\Actions\Projects\Audit\CancellationPayload;
use App\Actions\Projects\Audit\ProjectChangedPayload;
use App\Actions\Projects\Audit\ProjectKindChangedPayload;
use App\Actions\Projects\Audit\TaskChangedPayload;
use App\Enums\ApprovalRequestType;
use App\Enums\AttachmentAction;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\ProjectAction;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Enums\TaskAction;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\ProjectKind;
use App\Models\Task;

test('an audit payload round-trips through its stored array', function (AuditPayload $payload) {
    expect($payload::fromArray($payload->toArray()))->toEqual($payload);
})->with([
    'member added' => fn (): AuditPayload => new MemberAddedPayload(Role::Member),
    'member role changed' => fn (): AuditPayload => new MemberRoleChangedPayload(Role::Member, Role::Admin),
    'member removed' => fn (): AuditPayload => new MemberRemovedPayload('manager', true),
    'member left' => fn (): AuditPayload => new MemberLeftPayload('approval', 12),
    'contact created' => fn (): AuditPayload => new ContactRelationPayload('individual', OrganizationRelationshipType::Customer, ['code']),
    'contact updated' => fn (): AuditPayload => new ContactUpdatedPayload(['name', 'summary']),
    'contact deleted' => fn (): AuditPayload => new ContactDeletedPayload(true),
    'organization created' => fn (): AuditPayload => new OrganizationCreatedPayload(4, SubsidiaryKind::Branch),
    'organization updated' => fn (): AuditPayload => new OrganizationUpdatedPayload(['name'], true),
    'feature toggled' => fn (): AuditPayload => new FeatureToggledPayload('contact-gender', false),
    'approval' => fn (): AuditPayload => new ApprovalPayload(ApprovalRequestType::MembershipLeave, 'member_removed'),
    'project changed' => fn (): AuditPayload => new ProjectChangedPayload(ProjectAction::Created, 'Case 1'),
    'project kind changed' => fn (): AuditPayload => new ProjectKindChangedPayload('Case'),
    'cancellation' => fn (): AuditPayload => new CancellationPayload('No longer needed'),
    'task changed' => fn (): AuditPayload => new TaskChangedPayload(TaskAction::AssignedMember, 'Fery', 'Narji'),
    'task postponed' => fn (): AuditPayload => new TaskChangedPayload(TaskAction::Postponed, reason: 'Waiting for the permit'),
    'attachment changed' => fn (): AuditPayload => new AttachmentChangedPayload(AttachmentAction::Removed, 1, 'deed.pdf'),
]);

test('an audit payload summarizes itself from its event and subject', function () {
    $person = (new Person)->forceFill(['name' => 'Fery']);
    $task = (new Task)->forceFill(['title' => 'Review deed']);
    $kind = (new ProjectKind)->forceFill(['title' => 'Deed']);

    $label = ContactRelationPayload::fromEvent(AuditEvent::ContactRenewed, null, [
        'kind' => 'individual',
        'type' => 'customer',
    ]);

    $contact = ContactRelationPayload::fromEvent(AuditEvent::ContactCreated, $person, [
        'kind' => 'individual',
        'type' => 'customer',
        'fields' => ['code'],
    ]);

    $member = MemberAddedPayload::fromEvent(AuditEvent::MemberAdded, $person, [
        'role' => Role::Admin->value,
    ]);

    $assignment = TaskChangedPayload::fromEvent(AuditEvent::TaskChanged, $task, [
        'action' => 'assigned_member',
        'from' => 'Fery',
        'to' => 'Narji',
    ]);

    $taskCanceled = CancellationPayload::fromEvent(AuditEvent::TaskCanceled, $task, [
        'action' => 'canceled',
        'reason' => 'No longer needed',
    ]);

    $projectCanceled = CancellationPayload::fromEvent(AuditEvent::ProjectCanceled, null, [
        'action' => 'canceled',
        'reason' => 'No longer needed',
    ]);

    $kindChanged = ProjectKindChangedPayload::fromEvent(AuditEvent::ProjectKindChanged, $kind, [
        'action' => 'saved',
        'title' => 'Deed',
    ]);

    $attachment = AttachmentChangedPayload::fromEvent(AuditEvent::AttachmentChanged, $task, [
        'action' => 'added',
        'count' => 2,
    ]);

    expect((string) $label)->toBe(__(':type renewed', [
        'type' => OrganizationRelationshipType::Customer->label(),
    ]))
        ->and((string) $contact)->toBe(__(':type added', [
            'type' => OrganizationRelationshipType::Customer->label(),
        ]))
        ->and($contact->details())->toBe([
            __('Name: :name', ['name' => 'Fery']),
            __('Fields').': '.AuditLog::fieldLabel('code'),
        ])
        ->and((string) $member)->toBe(__(':role added', ['role' => Role::Admin->label()]))
        ->and($member->details())->toBe([__('Name: :name', ['name' => 'Fery'])])
        ->and((string) $assignment)->toBe(__(':Task reassigned from :from to :to', [
            'task' => 'Review deed',
            'from' => 'Fery',
            'to' => 'Narji',
        ]))
        ->and((string) $taskCanceled)->toBe(__(':task canceled', ['task' => 'Review deed']))
        ->and((string) $projectCanceled)->toBe(__('Project canceled'))
        ->and((string) $kindChanged)->toBe(__('Project kind :title updated', ['title' => 'Deed']))
        ->and((string) $attachment)->toBe(trans_choice(
            ':count attachment added to :target|:count attachments added to :target',
            2,
            ['target' => 'Review deed'],
        ));
});
