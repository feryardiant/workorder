@props(['projects', 'assignee' => false])

<flux:card body="divided">
    <flux:card.header>
        <flux:heading>{{ __('Projects') }}</flux:heading>
    </flux:card.header>

    <flux:card.body>
        @if ($projects->isEmpty())
            <flux:text class="text-sm">{{ __('No projects yet.') }}</flux:text>
        @else
            <flux:table :paginate="$projects" bleed pagination:class="p-[var(--flux-bleed)]">
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ $assignee ? __('Assignee') : __('Customer') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($projects as $project)
                        <flux:table.row :key="$project->id">
                            <flux:table.cell>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:link :href="route('projects.view', ['project' => $project->slug])" wire:navigate>
                                        {{ $project->title }}
                                    </flux:link>
                                    <flux:badge size="sm">{{ $project->kind?->title }}</flux:badge>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2 max-w-32">
                                    @if ($assignee)
                                        <flux:avatar tooltip size="xs" :src="$project->assignee->photo_url" :name="$project->assignee->name" />
                                        <span class="truncate">{{ $project->assignee->name }}</span>
                                    @else
                                        <flux:avatar tooltip size="xs" :src="$project->customer?->photo_url ?? $project->customer?->logo_url" :name="$project->customer?->name" />
                                        <span class="truncate">{{ $project->customer?->name ?? '—' }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center">
                                <flux:badge size="sm" :color="$project->status->color()">
                                    {{ $project->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card.body>
</flux:card>
