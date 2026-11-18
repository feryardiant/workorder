<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectKindTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task definition inside a project kind. Two levels only.
 *
 * @property int $id
 * @property int $project_kind_id
 * @property ?int $parent_id
 * @property ?int $contact_id
 * @property int $position
 * @property string $title
 * @property ?string $summary
 * @property-read ?ProjectKind $kind
 * @property-read ?self $parent
 * @property-read ?Organization $contact
 * @property-read Collection<int, self> $children
 */
#[Fillable(['project_kind_id', 'parent_id', 'contact_id', 'position', 'title', 'summary'])]
class ProjectKindTask extends Model
{
    /** @use HasFactory<ProjectKindTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<ProjectKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(ProjectKind::class, 'project_kind_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'contact_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Determine whether the row is a sub-task.
     */
    public function isSubTask(): bool
    {
        return $this->parent_id !== null;
    }
}
