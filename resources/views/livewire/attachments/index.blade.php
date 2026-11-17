<x-page :heading="__('Attachments')">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input
            type="search"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search files...')"
            :aria-label="__('Search attachments')"
        />

        <flux:select wire:model.live="projectId" :aria-label="__('Project')">
            <flux:select.option value="">{{ __('All projects') }}</flux:select.option>
            @foreach ($projects as $project)
                <flux:select.option value="{{ $project->id }}">{{ $project->title }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="taskId" :aria-label="__('Task')">
            <flux:select.option value="">{{ __('All tasks') }}</flux:select.option>
            @foreach ($tasks as $task)
                <flux:select.option value="{{ $task->id }}">{{ $task->title }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="type" :aria-label="__('File type')">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            <flux:select.option value="image">{{ __('Image') }}</flux:select.option>
            <flux:select.option value="pdf">{{ __('PDF') }}</flux:select.option>
            <flux:select.option value="document">{{ __('Document') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$attachments">
        <flux:table.columns>
            <flux:table.column class="w-8/12">{{ __('Title') }}</flux:table.column>
            <flux:table.column class="w-1/12" align="center">{{ __('Size') }}</flux:table.column>
            <flux:table.column class="w-2/12">{{ __('Attached to') }}</flux:table.column>
            <flux:table.column class="w-1/12">{{ __('Uploaded at') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($attachments as $attachment)
                <flux:table.row :key="$attachment->id">
                    <flux:table.cell>
                        <button type="button" wire:click="openEdit({{ $attachment->id }})" class="flex items-center gap-2 text-start">
                            <flux:icon
                                :icon="$attachment->isImage() ? 'photo' : ($attachment->isPdf() ? 'document-text' : 'document')"
                                class="size-5 text-zinc-500"
                            />
                            <span class="truncate">{{ $attachment->name }}</span>
                        </button>
                    </flux:table.cell>

                    <flux:table.cell align="center">{{ $attachment->human_size }}</flux:table.cell>

                    <flux:table.cell>
                        @if ($attachment->attachable instanceof \App\Models\Project)
                            <flux:link :href="route('projects.view', ['project' => $attachment->attachable->slug])" wire:navigate>
                                {{ $attachment->attachable->title }}
                            </flux:link>
                        @elseif ($attachment->attachable instanceof \App\Models\Task)
                            <flux:link :href="route('projects.view', ['project' => $attachment->attachable->project->slug]).'#task-'.$attachment->attachable->id" wire:navigate>
                                {{ $attachment->attachable->title }}
                            </flux:link>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>{{ $attachment->created_at?->isoFormat('DD MMM YYYY') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text>{{ __('No attachments found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <x-modal wire:model.self="showEdit" :title="$editTitle">
        @if ($editing !== null)
            <div class="space-y-4">
                <flux:input wire:model="editTitle" :label="__('Title')" />

                <flux:field>
                    <flux:label>{{ __('Attached to') }}</flux:label>
                    <flux:text>{{ $editing->attachable instanceof \App\Models\Project ? $editing->attachable->title : $editing->attachable?->title }}</flux:text>
                </flux:field>

                <flux:text class="text-sm">{{ $editing->mime }}</flux:text>
                <flux:text class="text-sm">{{ $editing->typeLabel() }} &middot; {{ $editing->human_size }}</flux:text>

                <div class="max-h-96 overflow-auto rounded-lg border border-zinc-200 dark:border-white/10">
                    @if ($editing->isImage())
                        <img src="{{ $editing->url }}" alt="{{ $editing->name }}" class="mx-auto max-h-96" />
                    @elseif ($editing->isPdf())
                        <iframe src="{{ $editing->url }}" title="{{ $editing->name }}" class="h-96 w-full"></iframe>
                    @else
                        <flux:text class="p-6 text-center">{{ __('No preview available') }}</flux:text>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="saveEdit">{{ __('Save') }}</flux:button>
                </div>
            </div>
        @endif
    </x-modal>
</x-page>
