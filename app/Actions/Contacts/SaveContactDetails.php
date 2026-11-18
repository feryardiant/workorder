<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Enums\ContactDetailType;
use App\Models\Entity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final class SaveContactDetails
{
    /**
     * Apply the submitted contact detail changes.
     *
     * Present keys are intent: a value upserts the record and an empty value
     * deletes it. Absent keys are untouched, so a stale form cannot revert a
     * value it never edited.
     *
     * @param  array<string, string|null>  $details
     * @return list<string> The detail types that changed.
     */
    public function handle(Entity $entity, array $details): array
    {
        $details = array_intersect_key($details, array_flip(array_map(
            static fn (ContactDetailType $type): string => $type->value,
            ContactDetailType::cases(),
        )));

        if ($details === []) {
            return [];
        }

        // Serialize concurrent first saves, clears, and entity deletion.
        $entity = $this->lock($entity);

        $changed = [];

        foreach (ContactDetailType::cases() as $type) {
            if (! \array_key_exists($type->value, $details)) {
                continue;
            }

            $field = $this->save($entity, $type, $details[$type->value]);

            if ($field !== null) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * Re-read the owning entity under a row lock.
     */
    private function lock(Entity $entity): Entity
    {
        $locked = $entity->newQuery()
            ->whereKey($entity->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel($entity::class, [$entity->getKey()]);
        }

        return $locked;
    }

    /**
     * Upsert or delete a single detail type.
     */
    private function save(Entity $entity, ContactDetailType $type, ?string $value): ?string
    {
        $value = $this->normalize($type, $value);
        $existing = $entity->contactDetails()->where('type', $type->value)->first();

        if ($value === null) {
            return $existing?->delete() === true ? $type->value : null;
        }

        if ($existing !== null) {
            if ($existing->value === $value) {
                return null;
            }

            $updated = $existing->newQuery()
                ->whereKey($existing->getKey())
                ->update(['value' => $value]);

            return $updated > 0 ? $type->value : null;
        }

        $entity->contactDetails()->create(['type' => $type, 'value' => $value]);

        return $type->value;
    }

    private function normalize(ContactDetailType $type, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $type === ContactDetailType::Email ? Str::lower($value) : $value;
    }
}
