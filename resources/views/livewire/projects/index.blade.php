<x-page :heading="__('Projects')">
    <x-slot:subheading>
        <flux:subheading>{{ __('Projects of :organization', ['organization' => $this->currentOrganization()?->name ?? __('No organization')]) }}</flux:subheading>
    </x-slot:subheading>

    <x-slot:actions>
        <div class="flex items-center gap-2">
            @if ($selected !== [])
                <flux:dropdown align="end">
                    <flux:button icon:trailing="chevron-down">
                        {{ __('Actions') }}
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item variant="danger" icon="archive-box" wire:click="confirmArchiveSelected">
                            {{ __('Archive selected projects') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif

            <flux:button variant="primary" icon="plus" wire:click="$set('showCreate', true)">
                {{ __('Add project') }}
            </flux:button>
        </div>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-6">
        <flux:input
            type="search"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search projects...')"
            :aria-label="__('Search projects')"
        />

        <x-ui.select wire:model.live="kindId" :aria-label="__('Kind')" :placeholder="__('All kinds')" :empty="__('No kinds found')" clearable>
            @foreach ($kinds as $kind)
                <x-ui.select.option value="{{ $kind->id }}">{{ $kind->title }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="customerId" :aria-label="__('Customer')" :placeholder="__('All customers')" :empty="__('No customers found')" clearable>
            @foreach ($customers as $customer)
                <x-ui.select.option value="{{ $customer->id }}" icon="building-office">{{ $customer->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="status" :aria-label="__('Status')" :placeholder="__('All statuses')" clearable>
            @foreach ($statuses as $case)
                <x-ui.select.option value="{{ $case->value }}">{{ $case->label() }}</x-ui.select.option>
            @endforeach
        </x-ui.select>

        <x-ui.date-picker wire:model.live="startedRange" mode="range" :aria-label="__('Started at')" :placeholder="__('Select Started at range')" clearable with-today />
        <x-ui.date-picker wire:model.live="finishedRange" mode="range" :aria-label="__('Finished at')" :placeholder="__('Select Finished at range')" clearable with-today />
    </div>

    <div class="overflow-x-auto">
    <flux:checkbox.group
        wire:key="projects-{{ $projects->currentPage() }}-{{ $selectionKey }}"
        wire:model.live="selected"
    >
    <flux:table :paginate="$projects">
        <flux:table.columns>
            <flux:table.column class="w-6/12">
                <span class="inline-flex items-center gap-2">
                    @if ($this->canSelectAny)
                        <label class="relative -m-1.5 inline-flex shrink-0 cursor-pointer items-center justify-center p-1.5">
                            <input
                                type="checkbox"
                                wire:change="toggleSelectAll"
                                @checked($allSelected)
                                x-init="const el = $el; const syncIndeterminate = () => el.indeterminate = $wire.someSelected; syncIndeterminate(); $wire.$watch('someSelected', syncIndeterminate)"
                                aria-label="{{ __('Select all projects') }}"
                                class="peer size-[1.125rem] cursor-pointer appearance-none rounded-[.3rem] border border-zinc-500 bg-white shadow-xs outline-offset-2 checked:border-zinc-800 checked:bg-zinc-800 indeterminate:border-zinc-800 indeterminate:bg-zinc-800 focus-visible:outline-2 focus-visible:outline-zinc-800 dark:border-white/50 dark:bg-white/10 dark:checked:border-white dark:checked:bg-white dark:indeterminate:border-white dark:indeterminate:bg-white dark:focus-visible:outline-white"
                            />

                            <svg class="pointer-events-none absolute hidden size-[.6rem] text-white peer-checked:block dark:text-zinc-800" viewBox="0 0 12 10" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 5 4.5 8.5 11 1" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <svg class="pointer-events-none absolute hidden h-0.5 w-[.55rem] rounded-full bg-white peer-indeterminate:block dark:bg-zinc-800" viewBox="0 0 10 2" fill="currentColor" aria-hidden="true">
                                <rect width="10" height="2" rx="1" />
                            </svg>
                        </label>
                    @endif

                    {{ __('Title') }}
                </span>
            </flux:table.column>
            <flux:table.column class="w-2/12">{{ __('Customer') }}</flux:table.column>
            <flux:table.column class="w-2/12">{{ __('Assignee') }}</flux:table.column>
            <flux:table.column class="w-1/12" align="center">{{ __('Status') }}</flux:table.column>
            <flux:table.column class="w-1/12" align="center">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($projects as $project)
                @php($isArchived = $project->trashed())
                @php($canManage = ! $isArchived && ($this->isManager || $project->assignee_id === auth()->user()?->person?->getKey()))

                <flux:table.row :key="$project->id">
                    <flux:table.cell class="w-8/12">
                        <div class="flex items-start gap-3">
                            @if ($canManage)
                                <span
                                    class="relative mt-0.5 flex size-[1.125rem] shrink-0 cursor-pointer items-center justify-center before:absolute before:-inset-y-3.5 before:-left-3.5 before:-right-2 before:content-['']"
                                    x-on:click="if (! $event.target.closest('ui-checkbox')) { $event.stopPropagation(); $el.querySelector('ui-checkbox').click() }"
                                >
                                    <flux:checkbox
                                        value="{{ $project->id }}"
                                        :aria-label="__('Select :name', ['name' => $project->title])"
                                        class="[&>[data-flux-checkbox-indicator]]:border-zinc-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-800 dark:[&>[data-flux-checkbox-indicator]]:border-white/50 dark:focus-visible:outline-white"
                                    />
                                </span>
                            @else
                                <span class="size-[1.125rem] shrink-0" aria-hidden="true"></span>
                            @endif

                            <div class="flex flex-col gap-1 w-full">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($isArchived)
                                        <span class="font-medium">{{ $project->title }}</span>
                                    @else
                                        <flux:link :href="route('projects.view', ['project' => $project->slug])" wire:navigate>
                                            <span class="font-medium">{{ $project->title }}</span>
                                        </flux:link>
                                    @endif
                                    <flux:badge size="sm">{{ $project->kind?->title }}</flux:badge>
                                </div>
                                @if (filled($project->summary))
                                    <flux:text class="text-wrap text-sm">{{ $project->summary }}</flux:text>
                                @endif
                                <flux:text class="text-xs text-zinc-500">
                                    <x-local-time :date="$project->created_at" />
                                    &middot; {{ $project->kind?->title }}
                                    &middot; {{ $project->code }}
                                </flux:text>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="w-1/12">
                        <div class="flex items-center gap-2">
                            <flux:avatar size="xs" :src="$project->customer?->logo_url" :name="$project->customer?->name" />
                            <span class="truncate">{{ $project->customer?->name }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="w-1/12">
                        @if ($project->assignee !== null)
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" :src="$project->assignee->photo_url" :name="$project->assignee->name" />
                                <span class="truncate">{{ $project->assignee->name }}</span>
                            </div>
                        @else
                            <flux:text>&mdash;</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="w-1/12" align="center">
                        <div class="flex flex-col items-center gap-1">
                            <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                            @if ($project->status->isOnGoing())
                                <flux:text class="text-xs">{{ __(':finished/:total tasks', ['finished' => $project->finished_tasks_count, 'total' => $project->total_tasks_count]) }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="w-1/12" align="center">
                        @if (! $isArchived)
                            <flux:dropdown align="end">
                                <flux:button size="sm" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('projects.view', ['project' => $project->slug])" wire:navigate>
                                        {{ __('View project') }}
                                    </flux:menu.item>
                                    @if ($canManage)
                                        <flux:menu.item variant="danger" icon="archive-box" wire:click="confirmArchive({{ $project->id }})">
                                            {{ __('Archive project') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>{{ $this->hasProjects ? __('No projects match your filters.') : __('No projects yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </flux:checkbox.group>
    </div>

    <div>
        <x-modal wire:model.self="showCreate" :title="__('New project')">
            <div class="flex flex-col gap-4">
                <x-ui.select wire:model="createKindId" :label="__('Kind')" :placeholder="__('Choose a kind...')" :empty="__('No kinds found')">
                    @foreach ($kinds as $kind)
                        <x-ui.select.option value="{{ $kind->id }}">{{ $kind->title }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model="createCustomerId" :label="__('Customer')" :placeholder="__('Choose a customer...')" :empty="__('No customers found')">
                    @foreach ($customers as $customer)
                        <x-ui.select.option value="{{ $customer->id }}" icon="building-office">{{ $customer->name }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>

                @if (blank($createCustomerId))
                    <flux:button
                        size="sm"
                        variant="subtle"
                        icon="plus"
                        wire:click="$dispatch('create-contact', { role: 'customer' })"
                    >
                        {{ __('Add customer') }}
                    </flux:button>
                @endif

                <x-ui.select wire:model="createAssigneeId" :label="__('Assignee')" :placeholder="__('Choose an assignee...')" :empty="__('No members found')">
                    @foreach ($members as $member)
                        <x-ui.select.option value="{{ $member->id }}" icon="user">{{ $member->name }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>

                <flux:textarea wire:model="createSummary" :label="__('Summary')" rows="3" />

                <x-ui.date-picker wire:model="createMandatedDate" :label="__('Mandated date')" clearable with-today />
                <x-ui.date-picker wire:model="createStartedDate" :label="__('Start date')" clearable with-today />

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="create">{{ __('Create project') }}</flux:button>
                </div>
            </div>
        </x-modal>

        <x-modal
            wire:model.self="showArchive"
            :title="__('Archive project')"
            :subtitle="__('The project is removed from lists but kept.')"
        >
            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="archive">{{ __('Archive') }}</flux:button>
            </div>
        </x-modal>

        <x-modal
            wire:model.self="showBatchArchive"
            :title="__('Archive projects')"
            :subtitle="__(':count selected projects will be archived.', ['count' => count($selected)])"
        >
            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="archiveSelected">{{ __('Archive') }}</flux:button>
            </div>
        </x-modal>

        <livewire:contacts.form />
    </div>
</x-page>
