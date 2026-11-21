<?php

declare(strict_types=1);

namespace App\Actions\Projects\Audit;

use App\Actions\Audit\AuditPayload;

final class ProjectKindChangedPayload extends AuditPayload
{
    public function __construct(public string $title) {}

    public function __toString(): string
    {
        return __('Project kind :title updated', ['title' => $this->subjectTitle()]);
    }

    public function toArray(): array
    {
        return [
            'action' => 'saved',
            'title' => $this->title,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['title']);
    }

    public function details(): array
    {
        return [];
    }
}
