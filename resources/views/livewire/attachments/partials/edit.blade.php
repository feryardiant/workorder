<x-modal wire:model.self="showModal" :title="$title" width="max-w-[min(56rem,calc(100%-2rem))]">
    @if ($attachment !== null)
        <div class="flex flex-col gap-4">
            <flux:input wire:model="title" :label="__('Title')" />

            <flux:field>
                <flux:label>{{ __('Attached to') }}</flux:label>
                <flux:text>{{ $attachment->attachable?->title }}</flux:text>
            </flux:field>

            <flux:text class="text-sm">{{ $attachment->mime }}</flux:text>
            <flux:text class="text-sm">{{ $attachment->typeLabel() }} &middot; {{ $attachment->human_size }}</flux:text>

            <div class="max-h-[70vh] overflow-auto rounded-lg border border-zinc-200 dark:border-white/10">
                @if ($attachment->isImage())
                    <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" class="mx-auto max-h-[70vh]" />
                @elseif ($attachment->isPdf())
                    <iframe src="{{ $attachment->url }}" title="{{ $attachment->name }}" class="h-[70vh] w-full"></iframe>
                @else
                    <flux:text class="p-6 text-center">{{ __('No preview available') }}</flux:text>
                @endif
            </div>

            <div class="sticky -bottom-6 -mx-6 -mb-6 flex items-center justify-between gap-4 border-t border-zinc-200 bg-white px-6 pt-4 pb-6 dark:border-white/10 dark:bg-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    @endif
</x-modal>
