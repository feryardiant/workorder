<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A project task or sub-task.
 *
 * @property int $id
 * @property int $project_id
 * @property ?int $parent_id
 * @property ?int $assignee_id
 * @property ?int $contact_id
 * @property string $slug
 * @property ?string $code
 * @property string $title
 * @property ?string $summary
 * @property ?string $remark
 * @property int $order
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property ?CarbonImmutable $canceled_at
 * @property-read TaskStatus $status
 * @property-read Project $project
 * @property-read ?Task $parent
 * @property-read Organization $contact
 * @property-read ?Person $assignee
 */
#[Fillable([
    'project_id',
    'parent_id',
    'assignee_id',
    'contact_id',
    'slug',
    'code',
    'title',
    'summary',
    'remark',
    'order',
    'started_at',
    'finished_at',
    'canceled_at',
])]
#[RouteKey('slug')]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function subTasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'contact_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Derive the task status from its timestamps.
     *
     * @return Attribute<TaskStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn (): TaskStatus => match (true) {
            $this->canceled_at !== null => TaskStatus::Canceled,
            $this->finished_at !== null => TaskStatus::Finished,
            $this->started_at === null || $this->started_at->isFuture() => TaskStatus::NotStarted,
            default => TaskStatus::OnGoing,
        });
    }

    /**
     * Determine whether the task is a sub-task.
     */
    public function isSubTask(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Determine whether the task has been canceled.
     */
    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    /**
     * Determine whether the task is disabled by a cancellation.
     */
    public function isDisabled(): bool
    {
        if ($this->project->isCanceled()) {
            return true;
        }

        if ($this->parent_id !== null && $this->parent !== null) {
            if ($this->parent->canceled_at !== null) {
                return true;
            }

            $order = $this->parent->order;
        } else {
            $order = $this->order;
        }

        return self::query()
            ->where('project_id', $this->project_id)
            ->whereNull('parent_id')
            ->where('order', '<', $order)
            ->whereNotNull('canceled_at')
            ->exists();
    }
}
