<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectKindFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable project definition: a title, summary and ordered task tree.
 *
 * @property int $id
 * @property int $owner_organization_id
 * @property string $title
 * @property ?string $summary
 */
#[Fillable(['owner_organization_id', 'title', 'summary'])]
class ProjectKind extends Model
{
    /** @use HasFactory<ProjectKindFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    /**
     * @return HasMany<ProjectKindTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectKindTask::class)->whereNull('parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<ProjectKindTask, $this>
     */
    public function allTasks(): HasMany
    {
        return $this->hasMany(ProjectKindTask::class)->orderBy('position');
    }
}
