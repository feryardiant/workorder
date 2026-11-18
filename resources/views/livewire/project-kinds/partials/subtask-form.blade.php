<x-modal wire:model.self="showModal" :title="$index === null ? __('Add sub-task') : __('Edit sub-task')">
    <div class="flex flex-col gap-4">
        <flux:input wire:model="title" :label="__('Title')" />
        <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />

        <x-ui.select wire:model="contactId" :label="__('Contact')" :placeholder="__('No contact')" clearable>
            @foreach ($this->contacts as $contact)
                <x-ui.select.option value="{{ $contact->id }}" icon="building-office">{{ $contact->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('Apply') }}</flux:button>
        </div>
    </div>
</x-modal>
