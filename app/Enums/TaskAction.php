<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The action recorded by a task audit event.
 */
enum TaskAction: string
{
    case Updated = 'updated';
    case Started = 'started';
    case Finished = 'finished';
    case Reopened = 'reopened';
    case Postponed = 'postponed';
    case AssignedMember = 'assigned_member';
    case AssignedContact = 'assigned_contact';

    public function isAssignment(): bool
    {
        return $this === self::AssignedMember || $this === self::AssignedContact;
    }
}
