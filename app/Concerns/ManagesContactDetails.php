<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\ContactDetailType;

trait ManagesContactDetails
{
    /**
     * Build the changed contact detail payload from the form state.
     *
     * Only changed values are submitted, so a stale form never overwrites a
     * newer value written by someone else.
     *
     * @return array<string, string>
     */
    protected function contactDetailChanges(string $email, string $phone, string $originalEmail, string $originalPhone): array
    {
        $changes = [];

        if ($email !== $originalEmail) {
            $changes[ContactDetailType::Email->value] = $email;
        }

        if ($phone !== $originalPhone) {
            $changes[ContactDetailType::Phone->value] = $phone;
        }

        return $changes;
    }
}
