<x-modal
    wire:model.self="showModal"
    flyout
    position="right"
    variant="floating"
    :title="$index === null ? __('Add task') : __('Edit task')"
>
    <div class="flex flex-col gap-4">
        <flux:input wire:model="title" :label="__('Title')" />
        <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />

        <x-ui.select wire:model="contactId" :label="__('Contact')" :placeholder="__('No contact')" clearable>
            @foreach ($this->contacts as $contact)
                <x-ui.select.option value="{{ $contact->id }}" icon="building-office">{{ $contact->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Sub-tasks') }}</flux:heading>
                <flux:button size="sm" icon="plus" variant="subtle" wire:click="addSubtask">{{ __('Add sub-task') }}</flux:button>
            </div>

            <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                @forelse ($subtasks as $subIndex => $subtask)
                    <div class="flex items-center justify-between gap-2 p-2" wire:key="sub-{{ $subIndex }}">
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $subtask['title'] }}</flux:heading>
                            @if (filled($subtask['summary'] ?? null))
                                <flux:text class="text-xs">{{ $subtask['summary'] }}</flux:text>
                            @endif
                        </div>

                        <flux:dropdown align="end">
                            <flux:button size="xs" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                            <flux:menu>
                                <flux:menu.item icon="arrow-up" wire:click="moveSubtask({{ $subIndex }}, -1)">{{ __('Move up') }}</flux:menu.item>
                                <flux:menu.item icon="arrow-down" wire:click="moveSubtask({{ $subIndex }}, 1)">{{ __('Move down') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="editSubtask({{ $subIndex }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item variant="danger" icon="trash" wire:click="deleteSubtask({{ $subIndex }})">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @empty
                    <flux:text class="p-3 text-sm">{{ __('No sub-tasks yet.') }}</flux:text>
                @endforelse
            </div>

            <flux:error name="subtasks" />
        </div>

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Close') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('Apply') }}</flux:button>
        </div>
    </div>

    <livewire:project-kinds.partials.subtask-form />
    <livewire:contacts.form />
</x-modal>
