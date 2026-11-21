<?php

declare(strict_types=1);

namespace App\Actions\Approvals\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\ApprovalRequestType;
use App\Models\AuditLog;

final class ApprovalPayload extends AuditPayload
{
    public function __construct(public ApprovalRequestType $type, public ?string $reason = null) {}

    public function toArray(): array
    {
        $data = ['type' => $this->type->value];

        if ($this->reason !== null && $this->reason !== '') {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $reason = $data['reason'] ?? null;

        return new self(
            ApprovalRequestType::from((string) $data['type']),
            \is_string($reason) ? $reason : null,
        );
    }

    public function details(): array
    {
        $details = [$this->type->label()];

        if ($this->reason !== null) {
            $details[] = __('Reason').': '.AuditLog::reasonLabel($this->reason);
        }

        return $details;
    }
}
