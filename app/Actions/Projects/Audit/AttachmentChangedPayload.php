<?php

declare(strict_types=1);

namespace App\Actions\Projects\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\AttachmentAction;
use App\Models\Task;

final class AttachmentChangedPayload extends AuditPayload
{
    public function __construct(
        public AttachmentAction $action,
        public int $count,
        public ?string $name = null,
    ) {}

    public function __toString(): string
    {
        return trans_choice(
            $this->action === AttachmentAction::Removed
                ? ':count attachment removed from :target|:count attachments removed from :target'
                : ':count attachment added to :target|:count attachments added to :target',
            $this->count,
            ['target' => $this->subjectTitle()],
        );
    }

    public function toArray(): array
    {
        $data = [
            'action' => $this->action->value,
            'count' => $this->count,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $name = $data['name'] ?? null;

        return new self(
            AttachmentAction::from((string) $data['action']),
            (int) $data['count'],
            \is_string($name) ? $name : null,
        );
    }

    public function details(): array
    {
        $project = $this->subject instanceof Task
            ? $this->subject->project()->withTrashed()->first()
            : $this->subject;

        return [
            __('Project: :project', ['project' => $project?->getAttribute('title')]),
        ];
    }
}
