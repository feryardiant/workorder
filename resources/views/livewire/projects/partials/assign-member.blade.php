<x-modal wire:model.self="showModal" :title="__('Assign member')">
    <div class="flex flex-col gap-4">
        <x-ui.select wire:model="memberId" :label="__('Member')" :placeholder="__('Choose a member...')" :empty="__('No members available.')">
            @foreach ($this->members as $member)
                <x-ui.select.option value="{{ $member->id }}" icon="user">{{ $member->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="assign">{{ __('Assign') }}</flux:button>
        </div>
    </div>
</x-modal>
