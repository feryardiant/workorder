<?php

declare(strict_types=1);

namespace App\Actions\Projects\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\AuditEvent;
use App\Models\AuditLog;

final class CancellationPayload extends AuditPayload
{
    public function __construct(public string $reason) {}

    public function __toString(): string
    {
        return match ($this->event) {
            AuditEvent::TaskCanceled => __(':task canceled', ['task' => $this->subjectTitle()]),
            default => __('Project canceled'),
        };
    }

    public function toArray(): array
    {
        return [
            'action' => 'canceled',
            'reason' => $this->reason,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['reason']);
    }

    public function details(): array
    {
        return [__('Reason').': '.AuditLog::reasonLabel($this->reason)];
    }
}
