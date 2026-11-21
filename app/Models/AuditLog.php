<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Audit\AuditPayload;
use App\Casts\AsAuditPayload;
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
 * @property ?AuditPayload $changes
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changes' => AsAuditPayload::class,
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
     * Get a human-readable label for an audit reason code.
     */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'role_changed' => __('Role changed'),
            'member_removed' => __('Member removed'),
            'member_left' => __('Member left'),
            'organization_deleted' => __('Organization deleted'),
            default => $reason,
        };
    }

    /**
     * Build a human-readable description of the recorded change.
     */
    public function summary(): string
    {
        $changes = $this->getAttribute('changes');

        return $changes instanceof AuditPayload ? (string) $changes : $this->event->label();
    }
}
