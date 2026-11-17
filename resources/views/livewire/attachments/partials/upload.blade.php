<x-modal wire:model.self="showModal" :title="__('Add attachments')">
    <div class="flex flex-col gap-4">
        <x-ui.file-upload
            wire:model="uploads"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp"
            :label="__('Attachments')"
            :description="__('PDF, documents or images up to 10 MB each')"
        >
            <x-ui.file-upload.dropzone with-progress />
        </x-ui.file-upload>

        @foreach ($uploads as $index => $upload)
            <x-ui.file-upload.item :heading="$upload->getClientOriginalName()" :size="$upload->getSize()">
                <x-slot:actions>
                    <x-ui.file-upload.item.remove
                        wire:click="removeUpload({{ $index }})"
                        :aria-label="__('Remove :name', ['name' => $upload->getClientOriginalName()])"
                    />
                </x-slot:actions>
            </x-ui.file-upload.item>
        @endforeach

        @if ($this->attachments->isNotEmpty())
            <div class="flex flex-col gap-2">
                <flux:heading size="sm">{{ __('Uploaded files') }}</flux:heading>

                @foreach ($this->attachments as $attachment)
                    <div wire:key="attachment-{{ $attachment->id }}" class="flex items-center justify-between gap-2">
                        <a href="{{ $attachment->url }}" target="_blank" class="truncate text-sm underline">{{ $attachment->name }}</a>
                        <flux:button size="xs" variant="subtle" icon="trash" wire:click="remove({{ $attachment->id }})">
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
        </div>
    </div>
</x-modal>
