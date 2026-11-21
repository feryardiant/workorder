<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The action recorded by an attachment audit event.
 */
enum AttachmentAction: string
{
    case Added = 'added';
    case Removed = 'removed';
}
