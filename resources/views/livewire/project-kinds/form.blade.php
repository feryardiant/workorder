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
                            <flux:table.column class="w-6/12">{{ __('Title') }}</flux:table.column>
                            <flux:table.column class="w-2/12">{{ __('Contacts') }}</flux:table.column>
                            <flux:table.column class="w-2/12 text-wrap">{{ __('No. of sub-tasks') }}</flux:table.column>
                            <flux:table.column class="w-1/12" align="center">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($tasks as $index => $task)
                                <flux:table.row :key="'task-'.$index">
                                    <flux:table.cell class="w-1/12" align="center">{{ $index + 1 }}</flux:table.cell>
                                    <flux:table.cell class="w-6/12" wire:click="editTask({{ $index }})">
                                        <flux:heading>{{ $task['title'] }}</flux:heading>
                                        @if (filled($task['summary']))
                                            <flux:text size="sm">{{ $task['summary'] }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="w-2/12" wire:click="editTask({{ $index }})">
                                        @php($contact = $task['contact'] ?? null)

                                        <div class="flex items-center gap-2">
                                            @if ($contact !== null)
                                                <flux:avatar size="xs" :src="$contact->photo_url" :name="$contact->name" />
                                                <span class="truncate">{{ $contact->name }}</span>
                                            @endif
                                        </div>
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

                <flux:card.body class="max-h-96 overflow-y-auto">
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

    <livewire:project-kinds.partials.task-form />
</x-page>
