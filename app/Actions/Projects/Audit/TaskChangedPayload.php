<?php

declare(strict_types=1);

namespace App\Actions\Projects\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\TaskAction;
use App\Models\Task;

final class TaskChangedPayload extends AuditPayload
{
    public function __construct(
        public TaskAction $action,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public function __toString(): string
    {
        return match ($this->action) {
            TaskAction::Started => __(':Task started', ['task' => $this->subjectTitle()]),
            TaskAction::Finished => __(':Task finished', ['task' => $this->subjectTitle()]),
            TaskAction::Reopened => __(':Task reopened', ['task' => $this->subjectTitle()]),
            TaskAction::Updated => __(':Task information updated', ['task' => $this->subjectTitle()]),
            TaskAction::AssignedMember => __(':Task reassigned from :from to :to', [
                'task' => $this->subjectTitle(),
                'from' => $this->from ?? __('unassigned'),
                'to' => $this->to ?? __('unassigned'),
            ]),
            TaskAction::AssignedContact => __(':Task contact changed from :from to :to', [
                'task' => $this->subjectTitle(),
                'from' => $this->from ?? __('unassigned'),
                'to' => $this->to ?? __('unassigned'),
            ]),
        };
    }

    public function toArray(): array
    {
        $data = ['action' => $this->action->value];

        if ($this->action->isAssignment()) {
            $data['from'] = $this->from;
            $data['to'] = $this->to;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        return new self(
            TaskAction::from((string) $data['action']),
            \is_string($from) ? $from : null,
            \is_string($to) ? $to : null,
        );
    }

    public function details(): array
    {
        $project = $this->subject instanceof Task
            ? ($this->subject->relationLoaded('project')
                ? $this->subject->project
                : $this->subject->project()->withTrashed()->first())
            : null;

        $details = [
            __('Project: :project', ['project' => $project?->getAttribute('title')]),
        ];

        if ($this->action->isAssignment()) {
            return [
                ...$details,
                ($this->from ?? __('unassigned')).' → '.($this->to ?? __('unassigned')),
            ];
        }

        return $details;
    }
}
