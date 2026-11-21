<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The action recorded by a project audit event.
 */
enum ProjectAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Finished = 'finished';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('Project created'),
            self::Finished => __('Project finished'),
            self::Archived => __('Project archived'),
            self::Updated => __('Project information updated'),
        };
    }
}
