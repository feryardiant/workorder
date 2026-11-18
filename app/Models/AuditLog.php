<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One recorded change inside an organization.
 *
 * @property int $id
 * @property ?int $organization_id
 * @property ?int $actor_id
 * @property AuditEvent $event
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?int $organization_relationship_period_id
 * @property array<string, mixed>|null $changes
 * @property Carbon $created_at
 * @property-read ?Organization $organization
 * @property-read ?User $actor
 * @property-read ?Model $subject
 * @property-read ?OrganizationRelationshipPeriod $period
 */
#[Fillable([
    'organization_id',
    'actor_id',
    'event',
    'subject_type',
    'subject_id',
    'organization_relationship_period_id',
    'changes',
    'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Build a human-readable description of the recorded change.
     */
    public function summary(): string
    {
        $title = (string) ($this->subject?->getAttribute('title') ?? $this->event->label());
        $changes = $this->getAttribute('changes') ?? [];
        $action = $changes['action'] ?? null;

        return match ($this->event) {
            AuditEvent::TaskChanged => match ($action) {
                'started' => __(':task started', ['task' => $title]),
                'finished' => __(':task finished', ['task' => $title]),
                'reopened' => __(':task reopened', ['task' => $title]),
                'updated' => __(':task information updated', ['task' => $title]),
                'assigned_member' => __(':task reassigned from :from to :to', [
                    'task' => $title,
                    'from' => $changes['from'] ?? __('unassigned'),
                    'to' => $changes['to'] ?? __('unassigned'),
                ]),
                'assigned_contact' => __(':task contact changed from :from to :to', [
                    'task' => $title,
                    'from' => $changes['from'] ?? __('unassigned'),
                    'to' => $changes['to'] ?? __('unassigned'),
                ]),
                default => __(':task updated', ['task' => $title]),
            },
            AuditEvent::TaskCanceled => __(':task canceled', ['task' => $title]),
            AuditEvent::ProjectChanged => match ($action) {
                'created' => __('Project created'),
                'finished' => __('Project finished'),
                'archived' => __('Project archived'),
                'updated' => __('Project information updated'),
                default => __('Project updated'),
            },
            AuditEvent::ProjectCanceled => __('Project canceled'),
            AuditEvent::ProjectKindChanged => __('Project kind :title updated', ['title' => $title]),
            AuditEvent::AttachmentChanged => $action === 'removed'
                ? trans_choice(':count attachment removed from :target|:count attachments removed from :target', (int) ($changes['count'] ?? 1), ['target' => $title])
                : trans_choice(':count attachment added to :target|:count attachments added to :target', (int) ($changes['count'] ?? 1), ['target' => $title]),
            default => $this->event->label(),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<OrganizationRelationshipPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(OrganizationRelationshipPeriod::class, 'organization_relationship_period_id');
    }

    /**
     * Get a human-readable label for a changed field name.
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'code' => __('Code'),
            'type' => __('Type'),
            'start_date' => __('Start date'),
            'finish_date' => __('Finish date'),
            'name' => __('Name'),
            'summary' => __('Summary'),
            'gender' => __('Gender'),
            'photo_url' => __('Photo'),
            'logo_url' => __('Organization Logo'),
            'contact_name' => __('Contact name'),
            'contact_summary' => __('Contact summary'),
            'contact_gender' => __('Contact gender'),
            default => $field,
        };
    }

    /**
     * Get a human-readable label for a feature toggle key.
     */
    public static function featureLabel(string $feature): string
    {
        return match ($feature) {
            'contact-gender' => __('Gender field in contact'),
            'profile-self-edit' => __('Profile self-edit'),
            'member-deletion' => __('Member removal'),
            'contact-deletion' => __('Contact deletion'),
            'registration' => __('Public registration'),
            default => $feature,
        };
    }

    /**
     * Get a human-readable label for the changed subject.
     */
    public function subjectLabel(): string
    {
        $subject = $this->subject;

        if ($subject instanceof ApprovalRequest) {
            return $subject->type->label();
        }

        if ($subject instanceof Person || $subject instanceof Organization) {
            return $subject->name;
        }

        $type = match ($this->subject_type) {
            Person::class => __('Person'),
            Organization::class => __('Organization'),
            ApprovalRequest::class => __('Approval request'),
            default => class_basename((string) $this->subject_type),
        };

        return $subject === null
            ? __('Deleted :type', ['type' => $type])
            : __(':type #:id', ['type' => $type, 'id' => $subject->getKey()]);
    }
}
