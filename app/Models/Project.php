<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A customer project built from a project kind.
 *
 * @property int $id
 * @property string $uuid
 * @property int $owner_organization_id
 * @property int $kind_id
 * @property int $customer_id
 * @property ?int $assignee_id
 * @property string $slug
 * @property string $code
 * @property string $title
 * @property ?string $summary
 * @property ?string $remark
 * @property array<string, mixed>|null $settings
 * @property ?CarbonImmutable $mandated_date
 * @property ?CarbonImmutable $started_date
 * @property ?CarbonImmutable $finished_date
 * @property ?CarbonImmutable $canceled_date
 * @property-read ProjectStatus $status
 * @property-read ?ProjectKind $kind
 * @property-read Organization $customer
 * @property-read Organization $ownerOrganization
 * @property-read ?Person $assignee
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Task> $allTasks
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable([
    'uuid',
    'owner_organization_id',
    'kind_id',
    'customer_id',
    'assignee_id',
    'slug',
    'code',
    'title',
    'summary',
    'remark',
    'settings',
    'mandated_date',
    'started_date',
    'finished_date',
    'canceled_date',
])]
#[RouteKey('slug')]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * {@inheritdoc}
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            $project->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'mandated_date' => 'immutable_date',
            'started_date' => 'immutable_date',
            'finished_date' => 'immutable_date',
            'canceled_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<ProjectKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(ProjectKind::class, 'kind_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assignee_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('order');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function allTasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('order');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Resolve a project slug inside the current tenant.
     *
     * @return ?self
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $organization = app(CurrentOrganization::class)->get();

        return self::query()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->when($organization !== null, fn ($query) => $query->where('owner_organization_id', $organization->getKey()))
            ->first();
    }

    /**
     * Derive the project status from its dates.
     *
     * @return Attribute<ProjectStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn (): ProjectStatus => match (true) {
            $this->canceled_date !== null => ProjectStatus::Canceled,
            $this->finished_date !== null => ProjectStatus::Finished,
            $this->started_date === null => ProjectStatus::NotStarted,
            $this->started_date->isFuture() => ProjectStatus::Scheduled,
            default => ProjectStatus::OnGoing,
        });
    }

    /**
     * Determine whether the project has been canceled.
     */
    public function isCanceled(): bool
    {
        return $this->canceled_date !== null;
    }

    /**
     * Determine whether the project has been finished.
     */
    public function isFinished(): bool
    {
        return $this->finished_date !== null;
    }

    /**
     * Get the finished and total top-level task counts.
     *
     * @return array{0: int, 1: int}
     */
    public function taskProgress(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        return [$tasks->whereNotNull('finished_at')->count(), $tasks->count()];
    }
}
