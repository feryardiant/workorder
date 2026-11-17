@if ($organization === null)
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex flex-1 items-center justify-center">
            <h1 class="mx-auto max-w-xl text-balance text-center text-4xl font-extralight text-zinc-400 dark:text-zinc-500">
                {{ __("You're not part of an organization yet.") }}
            </h1>
        </div>
    </div>
@else
    <x-page :heading="__('Home')">
        <x-slot:actions>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                <x-ui.date-picker
                    wire:model.live="range"
                    mode="range"
                    :label="__('Date range')"
                    label:sr-only
                    with-presets
                    start-day="0"
                    :today="$today"
                    :timezone="$timezone"
                    class="w-full sm:w-60"
                />
            </div>
        </x-slot:actions>

        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card body="divided">
                <flux:card.body class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:text>{{ __('New customers') }}</flux:text>
                        <flux:icon name="users" class="size-5 text-zinc-400" />
                    </div>

                    <flux:heading size="2xl">{{ $this->newCustomers }}</flux:heading>
                </flux:card.body>
            </flux:card>

            <flux:card body="divided">
                <flux:card.body class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:text>{{ __('On going projects') }}</flux:text>
                        <flux:icon name="folder" class="size-5 text-zinc-400" />
                    </div>

                    <flux:heading size="2xl">{{ $this->onGoingProjects }}</flux:heading>
                </flux:card.body>
            </flux:card>

            <flux:card body="divided">
                <flux:card.body class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:text>{{ __('Overdue') }}</flux:text>
                        <flux:icon name="exclamation-triangle" class="size-5 text-zinc-400" />
                    </div>

                    <flux:heading size="2xl">{{ $this->overdueProjects }}</flux:heading>
                </flux:card.body>
            </flux:card>

            <flux:card body="divided">
                <flux:card.body class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:text>{{ __('Due this week') }}</flux:text>
                        <flux:icon name="clock" class="size-5 text-zinc-400" />
                    </div>

                    <flux:heading size="2xl">{{ $this->dueThisWeek }}</flux:heading>
                </flux:card.body>
            </flux:card>
        </div>

        <div class="grid gap-6 lg:grid-cols-8">
            <flux:card class="min-h-96 lg:col-span-5" body="divided">
                <flux:card.header>
                    <flux:heading size="lg">{{ __('Projects needing attention') }}</flux:heading>
                </flux:card.header>

                <flux:card.body class="flex flex-col gap-4">
                @forelse ($this->projectsNeedingAttention as $project)
                    <div wire:key="attention-project-{{ $project->id }}" class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <a
                                href="{{ route('projects.view', ['project' => $project->slug]) }}"
                                wire:navigate
                                class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                            >
                                <flux:heading class="truncate">{{ $project->title }}</flux:heading>
                            </a>

                            <flux:text class="text-xs">{{ $project->customer?->name ?? '—' }}</flux:text>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <flux:text class="text-xs whitespace-nowrap">
                                {{ $project->finished_tasks_count }}/{{ $project->total_tasks_count }}
                            </flux:text>

                            <flux:badge size="sm" :color="$project->mandated_date?->isBefore(today()) ? 'red' : 'amber'">
                                {{ $project->mandated_date?->format('Y-m-d') }}
                            </flux:badge>
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                @endforelse
                </flux:card.body>
            </flux:card>

            <flux:card class="min-h-96 lg:col-span-3" body="divided">
                <flux:card.header>
                    <flux:heading size="lg">{{ __('My tasks') }}</flux:heading>
                </flux:card.header>

                <flux:card.body class="flex flex-col gap-4">
                @forelse ($this->myTasks as $task)
                    <div wire:key="my-task-{{ $task->id }}" class="min-w-0">
                        <a
                            href="{{ route('projects.view', ['project' => $task->project->slug]).'#task-'.$task->id }}"
                            wire:navigate
                            class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                        >
                            <flux:heading class="truncate">{{ $task->title }}</flux:heading>
                        </a>

                        <flux:text class="text-xs">{{ $task->project->title }}</flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                @endforelse
                </flux:card.body>
            </flux:card>
        </div>
    </x-page>
@endif
