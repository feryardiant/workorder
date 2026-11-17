<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle state of a task.
 */
enum TaskStatus: string
{
    case NotStarted = 'not_started';
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
            self::OnGoing => __('On going'),
            self::Finished => __('Finished'),
            self::Canceled => __('Canceled'),
        };
    }
}
