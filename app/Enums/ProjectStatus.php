<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle state of a project.
 */
enum ProjectStatus: string
{
    case NotStarted = 'not_started';
    case Scheduled = 'scheduled';
    case OnGoing = 'on_going';
    case Finished = 'finished';
    case Canceled = 'canceled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => __('Not started'),
            self::Scheduled => __('Scheduled'),
            self::OnGoing => __('On going'),
            self::Finished => __('Finished'),
            self::Canceled => __('Canceled'),
        };
    }
}
