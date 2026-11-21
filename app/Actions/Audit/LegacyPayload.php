<?php

declare(strict_types=1);

namespace App\Actions\Audit;

/**
 * Raw changes recorded before the typed payload refactor.
 *
 * Only used to drain events queued and rows written by the old format.
 */
final class LegacyPayload extends AuditPayload
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public array $changes) {}

    public function toArray(): array
    {
        return $this->changes;
    }

    public static function fromArray(array $data): static
    {
        return new self($data);
    }

    public function details(): array
    {
        return [];
    }
}
