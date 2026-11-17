<x-page :heading="$project->title">
    <x-slot:subheading>
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge size="sm">{{ $project->kind?->title }}</flux:badge>
            <flux:text class="text-zinc-500">
                <x-local-time :date="$project->created_at" /> &middot; {{ $project->code }}
            </flux:text>
        </div>
    </x-slot:subheading>

    <x-slot:actions>
        <flux:button icon="pencil-square" wire:click="openEdit">{{ __('Edit project') }}</flux:button>

        @if ($this->canFinish)
            <flux:button variant="primary" icon="check" wire:click="finishProject">{{ __('Finish') }}</flux:button>
        @endif

        <flux:dropdown align="end">
            <flux:button icon="ellipsis-horizontal" square :aria-label="__('More actions')" />
            <flux:menu>
                <flux:menu.item icon="paper-clip" wire:click="$dispatch('add-attachments', { type: 'project', id: {{ $project->id }} })">{{ __('Add attachments') }}</flux:menu.item>

                @if (! $project->status->isCanceled())
                    <flux:menu.item icon="no-symbol" wire:click="confirmCancelProject">{{ __('Cancel project') }}</flux:menu.item>
                @endif
                <flux:menu.item variant="danger" icon="archive-box" wire:click="confirmArchive">{{ __('Archive project') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </x-slot:actions>

    <div class="flex flex-col gap-6 lg:flex-row">
        <div class="flex flex-col gap-4 w-full md:w-5/8">
            @foreach ($tasks as $task)
                @php($disabled = $task->isDisabled() || $task->status->isCanceled())
                @php($locked = $disabled || $task->status->isCompleted())

                <flux:card variant="muted" body="divided" id="task-{{ $task->id }}" wire:key="task-{{ $task->id }}">
                    {{-- flex items-start justify-between gap-3 --}}
                    <flux:card.header class="">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="sm">{{ $task->title }}</flux:heading>

                                @if ($task->status->isCanceled())
                                    <flux:badge size="sm" color="red">{{ __('Canceled') }}</flux:badge>
                                @elseif ($disabled)
                                    <flux:badge size="sm" color="zinc">{{ __('Disabled') }}</flux:badge>
                                @endif
                            </div>
                            @if (filled($task->summary))
                                <flux:text class="text-sm">{{ $task->summary }}</flux:text>
                            @endif
                            @if (filled($task->remark))
                                <flux:text class="text-sm text-zinc-500">{{ $task->remark }}</flux:text>
                            @endif
                        </div>

                        <flux:card.actions>
                            @unless ($locked)
                                @if ($task->status->isNotStarted())
                                    <flux:button size="sm" variant="subtle" icon="play" wire:click="startTask({{ $task->id }})">{{ __('Start task') }}</flux:button>
                                @endif

                                @if ($task->status->isOnGoing())
                                    <flux:button size="sm" variant="subtle" icon="stop" wire:click="finishTask({{ $task->id }})">{{ __('Finish task') }}</flux:button>
                                @endif

                                <flux:dropdown align="end">
                                    <flux:button size="sm" icon="ellipsis-horizontal" square :aria-label="__('Task actions')" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="openTaskInfo({{ $task->id }})">{{ __('Update information') }}</flux:menu.item>
                                        <flux:menu.item icon="paper-clip" wire:click="$dispatch('add-attachments', { type: 'task', id: {{ $task->id }} })">{{ __('Add attachments') }}</flux:menu.item>
                                        <flux:menu.item icon="user-plus" wire:click="$dispatch('assign-member', { taskId: {{ $task->id }} })">{{ __('Assign member') }}</flux:menu.item>
                                        <flux:menu.item icon="building-office" wire:click="$dispatch('assign-contact', { taskId: {{ $task->id }} })">{{ __('Assign contact') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        @if (!$task->status->isCompleted())
                                            <flux:menu.item variant="danger" icon="no-symbol" wire:click="openCancel({{ $task->id }})">{{ __('Cancel task') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endunless
                        </flux:card.actions>
                    </flux:card.header>

                    @if($task->subTasks->isNotEmpty())
                        <flux:card.body>
                            <flux:table bleed>
                                <flux:table.rows>
                                    @foreach ($task->subTasks as $subTask)
                                        @php($subDisabled = $disabled || $subTask->status->isCanceled() || $task->status->isFinished())
                                        @php($subLocked = $subDisabled || $subTask->status->isCompleted())

                                        <flux:table.row>
                                            <flux:table.cell class="w-1/12">
                                                <flux:checkbox
                                                    :checked="$subTask->status->isFinished()"
                                                    :disabled="$subDisabled"
                                                    wire:click="toggleSubTask({{ $subTask->id }})"
                                                    :aria-label="__('Mark :title as finished', ['title' => $subTask->title])"
                                                    class="m-auto"
                                                />
                                            </flux:table.cell>

                                            <flux:table.cell class="w-10/12">
                                                <div class="min-w-0 flex-1">
                                                    <flux:heading size="sm">{{ $subTask->title }}</flux:heading>
                                                    @if (filled($subTask->summary))
                                                        <flux:text class="text-xs">{{ $subTask->summary }}</flux:text>
                                                    @endif
                                                </div>
                                            </flux:table.cell>

                                            <flux:table.cell class="w-1/12">
                                                @unless ($subLocked)
                                                    <flux:dropdown align="end">
                                                        <flux:button size="xs" icon="ellipsis-horizontal" square :aria-label="__('Sub-task actions')" />
                                                        <flux:menu>
                                                            @if ($subTask->status->isNotStarted())
                                                                <flux:menu.item icon="play" wire:click="startTask({{ $subTask->id }})">{{ __('Start task') }}</flux:menu.item>
                                                            @endif
                                                            <flux:menu.item icon="pencil-square" wire:click="openTaskInfo({{ $subTask->id }})">{{ __('Update information') }}</flux:menu.item>
                                                            <flux:menu.item icon="user-plus" wire:click="$dispatch('assign-member', { taskId: {{ $subTask->id }} })">{{ __('Assign member') }}</flux:menu.item>
                                                            <flux:menu.item icon="building-office" wire:click="$dispatch('assign-contact', { taskId: {{ $subTask->id }} })">{{ __('Assign contact') }}</flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endunless
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </flux:card.body>
                    @endif

                    <flux:card.footer>
                        <div class="flex flex-wrap items-center gap-4">
                            @if ($locked)
                                <span class="flex items-center gap-2 text-sm">
                                    <flux:avatar
                                        size="xs"
                                        :src="$task->contact?->logo_url"
                                        :name="$task->contact?->name"
                                        :initials="$task->contact?->initials()"
                                    />
                                    {{ $task->contact?->name ?? __('No contact') }}
                                </span>

                                <span class="flex items-center gap-2 text-sm">
                                    <flux:avatar
                                        size="xs"
                                        :src="$task->assignee?->photo_url"
                                        :name="$task->assignee?->name"
                                        :initials="$task->assignee?->initials()"
                                    />
                                    {{ $task->assignee?->name ?? __('Unassigned') }}
                                </span>
                            @else
                                <flux:button size="sm" variant="subtle" wire:click="$dispatch('assign-contact', { taskId: {{ $task->id }} })">
                                    <span class="flex items-center gap-2">
                                        <flux:avatar
                                            size="xs"
                                            :src="$task->contact?->logo_url"
                                            :name="$task->contact?->name"
                                            :initials="$task->contact?->initials()"
                                        />
                                        {{ $task->contact?->name ?? __('Assign contact') }}
                                    </span>
                                </flux:button>

                                <flux:button size="sm" variant="subtle" wire:click="$dispatch('assign-member', { taskId: {{ $task->id }} })">
                                    <span class="flex items-center gap-2">
                                        <flux:avatar
                                            size="xs"
                                            :src="$task->assignee?->photo_url"
                                            :name="$task->assignee?->name"
                                            :initials="$task->assignee?->initials()"
                                        />
                                        {{ $task->assignee?->name ?? __('Assign member') }}
                                    </span>
                                </flux:button>
                            @endif

                            <flux:text class="ms-auto text-xs">
                                {{ $task->attachments_count }} {{ $task->attachments_count === 1 ? __('attachment') : __('attachments') }}
                            </flux:text>
                        </div>
                    </flux:card.footer>
                </flux:card>
            @endforeach
        </div>

        <aside class="flex flex-col gap-4 w-full md:w-3/8">
            <flux:card body="divided">
                <flux:card.body class="flex flex-col gap-4">
                    @if (filled($project->summary))
                        <flux:text class="text-base/7">{{ $project->summary }}</flux:text>
                    @endif
                </flux:card.body>

                <flux:card.footer>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex flex-1 items-center gap-2">
                            <flux:avatar size="sm" :src="$project->customer?->logo_url" :name="$project->customer?->name" :initials="$project->customer?->initials()" />
                            <span class="truncate">{{ $project->customer?->name }}</span>
                        </div>

                        @if ($assignees->isNotEmpty())
                            <flux:avatar.group>
                                @foreach ($assignees as $person)
                                    <flux:avatar size="sm" :src="$person->photo_url" :name="$person->name" :initials="$person->initials()" />
                                @endforeach
                            </flux:avatar.group>
                        @endif
                    </div>
                </flux:card.footer>
            </flux:card>

            <flux:card body="divided">
                <flux:card.header>
                    <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                </flux:card.header>

                <flux:card.body>
                    @if ($projectAttachments->isEmpty())
                        <flux:text class="text-sm">{{ __('No attachments yet.') }}</flux:text>
                    @else
                        <flux:table bleed>
                            <flux:table.rows>
                                @foreach ($projectAttachments as $attachment)
                                    <flux:table.row :key="'project-attachment-'.$attachment->id">
                                        <flux:table.cell>
                                            <button
                                                type="button"
                                                wire:click="$dispatch('edit-attachment', { attachmentId: {{ $attachment->id }} })"
                                                class="flex min-w-0 items-center gap-2 text-start"
                                            >
                                                <flux:icon
                                                    :icon="$attachment->isImage() ? 'photo' : ($attachment->isPdf() ? 'document-text' : 'document')"
                                                    class="size-4 shrink-0 text-zinc-500"
                                                />
                                                <span class="truncate text-sm">{{ $attachment->name }}</span>
                                            </button>
                                        </flux:table.cell>
                                        <flux:table.cell align="end" class="text-xs whitespace-nowrap">
                                            {{ $attachment->human_size }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:card.body>
            </flux:card>

            <flux:card body="divided">
                <flux:card.header>
                    <flux:heading size="sm">{{ __('Project histories') }}</flux:heading>
                </flux:card.header>

                <flux:card.body class="max-h-96 overflow-y-auto">
                    @if ($timeline->isEmpty())
                        <flux:text class="text-sm">{{ __('No histories yet.') }}</flux:text>
                    @else
                        <x-ui.timeline>
                            @foreach ($timeline as $log)
                                <x-ui.timeline.item :status="$loop->first ? 'current' : 'complete'">
                                    <flux:heading size="sm">{{ $log->summary() }}</flux:heading>
                                    <flux:text class="text-xs">
                                        {{ $log->actor?->name }} &middot; {{ $log->created_at->diffForHumans() }}
                                    </flux:text>
                                </x-ui.timeline.item>
                            @endforeach
                        </x-ui.timeline>
                    @endif
                </flux:card.body>
            </flux:card>
        </aside>
    </div>

    <div>
        <x-modal wire:model.self="showEdit" :title="__('Edit project')">
            <div class="flex flex-col gap-4">
                <flux:input wire:model="title" :label="__('Title')" />

            <x-ui.select wire:model="customerId" :label="__('Customer')" :placeholder="__('Choose a customer...')" :empty="__('No customers found')">
                @foreach ($customers as $customer)
                    <x-ui.select.option value="{{ $customer->id }}" icon="building-office">{{ $customer->name }}</x-ui.select.option>
                @endforeach
            </x-ui.select>
                <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />
                <flux:textarea wire:model="remark" :label="__('Remark')" rows="2" />
                <x-ui.date-picker wire:model="mandatedDate" :label="__('Mandated date')" clearable with-today />

                <x-ui.select wire:model="assigneeId" :label="__('Assignee')" :placeholder="__('Unassigned')" :empty="__('No members found')" clearable>
                    @foreach ($members as $member)
                        <x-ui.select.option value="{{ $member->id }}" icon="user">{{ $member->name }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="saveProject">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </x-modal>

        <x-modal wire:model.self="showTaskInfo" :title="__('Update information')">
            <div class="flex flex-col gap-4">
                <flux:input wire:model="taskTitle" :label="__('Title')" />
                <flux:textarea wire:model="taskSummary" :label="__('Summary')" rows="3" />

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="saveTaskInfo">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </x-modal>

        <x-modal wire:model.self="showCancel" :title="__('Cancel task')">
            <div class="flex flex-col gap-4">
                <flux:textarea wire:model="cancelReason" :label="__('Reason')" rows="3" required />
                <flux:error name="reason" />

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="cancelTask">{{ __('Cancel task') }}</flux:button>
                </div>
            </div>
        </x-modal>

        <x-modal wire:model.self="showCancelProject" :title="__('Cancel project')">
            <div class="flex flex-col gap-4">
                <flux:textarea wire:model="projectCancelReason" :label="__('Reason')" rows="3" required />
                <flux:error name="reason" />

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="cancelProject">{{ __('Cancel project') }}</flux:button>
                </div>
            </div>
        </x-modal>

        <x-modal wire:model.self="showArchive" :title="__('Archive project')" :subtitle="__('The project is removed from lists but kept.')">
            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="archive">{{ __('Archive') }}</flux:button>
            </div>
        </x-modal>

        <livewire:projects.partials.assign-member />
        <livewire:attachments.partials.upload />
        <livewire:attachments.partials.edit />
        <livewire:projects.partials.assign-contact />
    </div>
</x-page>
