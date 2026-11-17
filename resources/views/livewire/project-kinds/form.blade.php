<x-page :heading="$kindId === null ? __('Create new project kind') : __('Edit: :title', ['title' => $title])">
    <x-slot:actions>
        @if ($this->isManager)
            <flux:button variant="primary" icon="check" wire:click="save">{{ __('Save') }}</flux:button>
        @endif
    </x-slot:actions>

    <div class="flex gap-6">
        <div class="flex flex-col gap-4 w-full md:w-5/8">
            <flux:card class="flex flex-col gap-4">
                <flux:input wire:model="title" :label="__('Title')" :disabled="! $this->isManager" />
                <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" :disabled="! $this->isManager" />
            </flux:card>

            <flux:card body="divided">
                <flux:card.header class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('Tasks') }}</flux:heading>

                    <flux:card.actions>
                        @if ($this->isManager)
                            <flux:button size="sm" icon="plus" wire:click="addTask">{{ __('Add task') }}</flux:button>
                        @endif
                    </flux:card.actions>
                </flux:card.header>

                <flux:card.body>
                    <flux:table bleed>
                        <flux:table.columns>
                            <flux:table.column class="w-1/12">{{ __('Order') }}</flux:table.column>
                            <flux:table.column class="w-8/12">{{ __('Title') }}</flux:table.column>
                            <flux:table.column class="w-2/12">{{ __('No. of sub-tasks') }}</flux:table.column>
                            <flux:table.column class="w-1/12" align="center">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($tasks as $index => $task)
                                <flux:table.row :key="'task-'.$index">
                                    <flux:table.cell class="w-1/12" align="center">{{ $index + 1 }}</flux:table.cell>
                                    <flux:table.cell class="w-8/12">
                                        <flux:heading>{{ $task['title'] }}</flux:heading>
                                        @if (filled($task['summary']))
                                            <flux:text size="sm">{{ $task['summary'] }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="w-2/12" align="center">{{ count($task['subtasks']) }}</flux:table.cell>
                                    <flux:table.cell class="w-1/12" align="center">
                                        @if ($this->isManager)
                                            <flux:dropdown align="end">
                                                <flux:button size="xs" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                                                <flux:menu>
                                                    <flux:menu.item icon="arrow-up" wire:click="moveTask({{ $index }}, -1)">{{ __('Move up') }}</flux:menu.item>
                                                    <flux:menu.item icon="arrow-down" wire:click="moveTask({{ $index }}, 1)">{{ __('Move down') }}</flux:menu.item>
                                                    <flux:menu.item icon="pencil-square" wire:click="editTask({{ $index }})">{{ __('Edit') }}</flux:menu.item>
                                                    <flux:menu.item variant="danger" icon="trash" wire:click="deleteTask({{ $index }})">{{ __('Delete') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4">
                                        <flux:text>{{ __('No tasks yet.') }}</flux:text>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card.body>

                <flux:error name="tasks" />
            </flux:card>
        </div>

        <div class="flex flex-col gap-4 w-full md:w-3/8">
            <flux:card>
                <flux:card.header>
                    <flux:heading size="sm">{{ __('Project histories') }}</flux:heading>
                </flux:card.header>

                <flux:card.body>
                    @if ($timeline->isEmpty())
                        <flux:text class="text-sm">{{ __('No histories yet.') }}</flux:text>
                    @else
                        <x-ui.timeline>
                            @foreach ($timeline as $log)
                                <x-ui.timeline.item :status="$loop->first ? 'current' : 'complete'">
                                    <flux:heading size="sm">{{ $log->event->label() }}</flux:heading>
                                    <flux:text class="text-xs">
                                        {{ $log->actor?->name }} &middot; {{ $log->created_at->diffForHumans() }}
                                    </flux:text>
                                </x-ui.timeline.item>
                            @endforeach
                        </x-ui.timeline>
                    @endif
                </flux:card.body>
            </flux:card>
        </div>
    </div>

    <x-modal wire:model.self="showTaskForm" flyout position="right" variant="floating" :title="$editingTask === null ? __('Add task') : __('Edit task')">
        <div class="flex flex-col gap-4">
            <flux:input wire:model="taskTitle" :label="__('Title')" />
            <flux:textarea wire:model="taskSummary" :label="__('Summary')" rows="3" />

            @if ($editingTask !== null)
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Sub-tasks') }}</flux:heading>
                        <flux:button size="sm" icon="plus" variant="subtle" wire:click="addSubtask({{ $editingTask }})">
                            {{ __('Add sub-task') }}
                        </flux:button>
                    </div>

                    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                        @forelse ($tasks[$editingTask]['subtasks'] as $subIndex => $subtask)
                            <div class="flex items-center justify-between gap-2 p-2" wire:key="sub-{{ $subIndex }}">
                                <div class="min-w-0">
                                    <flux:heading size="sm">{{ $subtask['title'] }}</flux:heading>
                                    @if (filled($subtask['summary']))
                                        <flux:text class="text-xs">{{ $subtask['summary'] }}</flux:text>
                                    @endif
                                </div>

                                <flux:dropdown align="end">
                                    <flux:button size="xs" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="arrow-up" wire:click="moveSubtask({{ $editingTask }}, {{ $subIndex }}, -1)">{{ __('Move up') }}</flux:menu.item>
                                        <flux:menu.item icon="arrow-down" wire:click="moveSubtask({{ $editingTask }}, {{ $subIndex }}, 1)">{{ __('Move down') }}</flux:menu.item>
                                        <flux:menu.item icon="pencil-square" wire:click="editSubtask({{ $editingTask }}, {{ $subIndex }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item variant="danger" icon="trash" wire:click="deleteSubtask({{ $editingTask }}, {{ $subIndex }})">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        @empty
                            <flux:text class="p-3 text-sm">{{ __('No sub-tasks yet.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            @else
                <flux:text class="text-sm">{{ __('Save the task first to add sub-tasks.') }}</flux:text>
            @endif

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="saveTask">{{ __('Apply') }}</flux:button>
            </div>
        </div>
    </x-modal>

    <x-modal wire:model.self="showSubtaskForm" :title="$editingSubtask === null ? __('Add sub-task') : __('Edit sub-task')">
        <div class="flex flex-col gap-4">
            <flux:input wire:model="subtaskTitle" :label="__('Title')" />
            <flux:textarea wire:model="subtaskSummary" :label="__('Summary')" rows="3" />

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="saveSubtask">{{ __('Apply') }}</flux:button>
            </div>
        </div>
    </x-modal>
</x-page>
