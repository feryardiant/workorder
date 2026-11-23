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
                <flux:button
                    icon="x-mark"
                    size="xs"
                    variant="ghost"
                    class="[@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                    wire:click.stop="removeQueuedUpload({{ $index }})"
                    :aria-label="__('Remove :name', ['name' => $upload->getClientOriginalName()])"
                />
            </x-ui.file-upload.item>
        @endforeach

        @foreach ($this->attachments as $attachment)
            <x-ui.file-upload.item
                wire:key="attachment-{{ $attachment->id }}"
                :heading="$attachment->title"
                :size="$attachment->size"
            >
                <flux:button
                    icon="x-mark"
                    size="xs"
                    variant="ghost"
                    class="[@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                    wire:click.stop="remove({{ $attachment->id }})"
                    :aria-label="__('Remove :name', ['name' => $attachment->title])"
                />
            </x-ui.file-upload.item>
        @endforeach

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
        </div>
    </div>
</x-modal>
