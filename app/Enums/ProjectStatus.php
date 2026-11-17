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

    public function isCompleted(): bool
    {
        return $this->isCanceled() || $this->isFinished();
    }

    public function isNotStarted(): bool
    {
        return $this === self::NotStarted;
    }

    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
    }

    public function isFinished(): bool
    {
        return $this === self::Finished;
    }

    public function isOnGoing(): bool
    {
        return $this === self::OnGoing;
    }

    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }

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
