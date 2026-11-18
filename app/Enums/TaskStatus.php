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

    public function isCompleted(): bool
    {
        return $this->isCanceled() || $this->isFinished();
    }

    public function isNotStarted(): bool
    {
        return $this === self::NotStarted;
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
     * Get the badge color for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'zinc',
            self::OnGoing => 'sky',
            self::Finished => 'green',
            self::Canceled => 'red',
        };
    }

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
