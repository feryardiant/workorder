<?php

declare(strict_types=1);

namespace App\Actions\Projects\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\ProjectAction;

final class ProjectChangedPayload extends AuditPayload
{
    public function __construct(public ProjectAction $action, public ?string $title = null) {}

    public function __toString(): string
    {
        return $this->action->label();
    }

    public function toArray(): array
    {
        $data = ['action' => $this->action->value];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $title = $data['title'] ?? null;

        return new self(
            ProjectAction::from((string) $data['action']),
            \is_string($title) ? $title : null,
        );
    }

    public function details(): array
    {
        return [
            __('Project: :project', ['project' => $this->subjectTitle()]),
        ];
    }
}
