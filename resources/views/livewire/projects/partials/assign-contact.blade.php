<x-modal wire:model.self="showModal" :title="__('Assign contact')">
    <div class="flex flex-col gap-4">
        <x-ui.select wire:model="contactId" :label="__('Contact')" :placeholder="__('Choose a contact...')" :empty="__('No contacts available.')" clearable>
            @foreach ($this->contacts as $contact)
                <x-ui.select.option value="{{ $contact->id }}" icon="building-office">{{ $contact->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <flux:button variant="subtle" icon="plus" wire:click="$dispatch('create-contact', { role: 'vendor' })">
            {{ __('Add contact') }}
        </flux:button>

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="assign">{{ __('Assign') }}</flux:button>
        </div>
    </div>

    <livewire:contacts.form />
</x-modal>
