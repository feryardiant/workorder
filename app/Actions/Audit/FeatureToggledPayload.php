<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;

final class FeatureToggledPayload extends AuditPayload
{
    public function __construct(public string $feature, public bool $enabled) {}

    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'enabled' => $this->enabled,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['feature'], (bool) $data['enabled']);
    }

    public function details(): array
    {
        return [__(':feature: :state', [
            'feature' => AuditLog::featureLabel($this->feature),
            'state' => $this->enabled ? __('On') : __('Off'),
        ])];
    }
}
