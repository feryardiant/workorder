<x-page :heading="$project->title">
    <x-slot:subheading>
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge size="sm">{{ $project->kind?->title }}</flux:badge>
            <flux:text class="text-zinc-500">
                {{ $project->created_at?->isoFormat('DD MMM YYYY') }} &middot; {{ $project->code }}
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
                @if ($project->status !== \App\Enums\ProjectStatus::Canceled)
                    <flux:menu.item icon="no-symbol" wire:click="confirmCancelProject">{{ __('Cancel project') }}</flux:menu.item>
                @endif
                <flux:menu.item variant="danger" icon="archive-box" wire:click="confirmArchive">{{ __('Archive project') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </x-slot:actions>

    <div class="flex gap-6">
        <div class="flex flex-col gap-4 w-full md:w-5/8">
            @foreach ($tasks as $task)
                @php($disabled = $task->isDisabled() || $task->isCanceled())

                <flux:card variant="muted" body="divided" id="task-{{ $task->id }}" wire:key="task-{{ $task->id }}">
                    {{-- flex items-start justify-between gap-3 --}}
                    <flux:card.header class="">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="sm">{{ $task->title }}</flux:heading>

                                @if ($task->isCanceled())
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
                            @unless ($disabled)
                                <flux:dropdown align="end">
                                    <flux:button size="sm" icon="ellipsis-horizontal" square :aria-label="__('Task actions')" />

                                    <flux:menu>
                                        @if ($task->started_at === null || $task->started_at->isFuture())
                                            <flux:menu.item icon="play" wire:click="startTask({{ $task->id }})">{{ __('Start task') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="pencil-square" wire:click="openTaskInfo({{ $task->id }})">{{ __('Update information') }}</flux:menu.item>
                                        <flux:menu.item icon="user-plus" wire:click="openMember({{ $task->id }})">{{ __('Assign member') }}</flux:menu.item>
                                        <flux:menu.item icon="building-office" wire:click="openContact({{ $task->id }})">{{ __('Assign contact') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="no-symbol" wire:click="openCancel({{ $task->id }})">{{ __('Cancel task') }}</flux:menu.item>
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
                                        @php($subDisabled = $disabled || $subTask->isCanceled())

                                        <flux:table.row>
                                            <flux:table.cell class="w-1/12">
                                                <flux:checkbox
                                                    :checked="$subTask->finished_at !== null"
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
                                                @unless ($subDisabled)
                                                    <flux:dropdown align="end">
                                                        <flux:button size="xs" icon="ellipsis-horizontal" square :aria-label="__('Sub-task actions')" />
                                                        <flux:menu>
                                                            @if ($subTask->started_at === null || $subTask->started_at->isFuture())
                                                                <flux:menu.item icon="play" wire:click="startTask({{ $subTask->id }})">{{ __('Start task') }}</flux:menu.item>
                                                            @endif
                                                            <flux:menu.item icon="pencil-square" wire:click="openTaskInfo({{ $subTask->id }})">{{ __('Update information') }}</flux:menu.item>
                                                            <flux:menu.item icon="user-plus" wire:click="openMember({{ $subTask->id }})">{{ __('Assign member') }}</flux:menu.item>
                                                            <flux:menu.item icon="building-office" wire:click="openContact({{ $subTask->id }})">{{ __('Assign contact') }}</flux:menu.item>
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

                    @unless ($disabled)
                        <flux:card.footer>
                            <div class="flex flex-wrap items-center gap-4">
                                <flux:button size="sm" variant="subtle" wire:click="openContact({{ $task->id }})">
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

                                <flux:button size="sm" variant="subtle" wire:click="openMember({{ $task->id }})">
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
                            </div>
                        </flux:card.footer>
                    @endunless
                </flux:card>
            @endforeach
        </div>

        <aside class="flex flex-col gap-4 w-full md:w-3/8">
            <flux:card>
                @if (filled($project->summary))
                    <flux:card.header>
                        <flux:text class="text-base/7">{{ $project->summary }}</flux:text>
                    </flux:card.header>
                @endif

                <flux:card.body>
                    @if ($assignees->isNotEmpty())
                        <flux:avatar.group>
                            @foreach ($assignees as $person)
                                <flux:avatar size="sm" :src="$person->photo_url" :name="$person->name" :initials="$person->initials()" />
                            @endforeach
                        </flux:avatar.group>
                    @endif
                </flux:card.body>
            </flux:card>

            <flux:card>
                <flux:card.header>
                    <flux:heading size="sm">{{ __('Project histories') }}</flux:heading>
                </flux:card.header>

                <flux:card.body>
                    @if ($timeline->isEmpty())
                        <flux:text class="text-sm">{{ __('No histories yet.') }}</flux:text>
                    @else
                        <x-ui.timeline>
                            @foreach ($timeline as $log)
                                <x-ui.timeline.item :status="$loop->first ? 'current' : 'complete'">
                                    <flux:heading size="sm">{{ $log->event->label() }}</flux:heading>
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

    <x-modal wire:model.self="showEdit" :title="__('Edit project')">
        <div class="space-y-4">
            <flux:input wire:model="title" :label="__('Title')" />
            <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />
            <flux:textarea wire:model="remark" :label="__('Remark')" rows="2" />
            <x-ui.date-picker wire:model="mandatedDate" :label="__('Mandated date')" clearable with-today />

            <x-ui.select wire:model="assigneeId" :label="__('Assignee')" :placeholder="__('Unassigned')" :empty="__('No members found')" clearable>
                @foreach ($members as $member)
                    <x-ui.select.option value="{{ $member->id }}" icon="user">{{ $member->name }}</x-ui.select.option>
                @endforeach
            </x-ui.select>

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
                <x-ui.file-upload.item
                    :heading="$upload->getClientOriginalName()"
                    :size="$upload->getSize()"
                >
                    <x-slot:actions>
                        <x-ui.file-upload.item.remove
                            wire:click="removeUpload({{ $index }})"
                            :aria-label="__('Remove :name', ['name' => $upload->getClientOriginalName()])"
                        />
                    </x-slot:actions>
                </x-ui.file-upload.item>
            @endforeach

            @if ($project->attachments->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($project->attachments as $attachment)
                        <div wire:key="attachment-{{ $attachment->id }}" class="flex items-center justify-between gap-2">
                            <a href="{{ $attachment->url }}" target="_blank" class="truncate text-sm underline">{{ $attachment->name }}</a>
                            <flux:button size="xs" variant="subtle" icon="trash" wire:click="removeAttachment({{ $attachment->id }})">
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
                <flux:button variant="primary" wire:click="saveProject">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </x-modal>

    <x-modal wire:model.self="showTaskInfo" :title="__('Update information')">
        <div class="space-y-4">
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

    <x-modal wire:model.self="showMember" :title="__('Assign member')">
        <div class="space-y-4">
            <flux:radio.group wire:model="memberId">
                @foreach ($assignableMembers as $member)
                    <flux:radio :value="$member->id" :label="$member->name" />
                @endforeach
            </flux:radio.group>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="assignMember">{{ __('Assign') }}</flux:button>
            </div>
        </div>
    </x-modal>

    <x-modal wire:model.self="showContact" :title="__('Assign contact')">
        <div class="space-y-4">
            <flux:radio.group wire:model="contactId">
                @foreach ($contacts as $contact)
                    <flux:radio :value="$contact->id" :label="$contact->name" />
                @endforeach
            </flux:radio.group>

            <flux:button variant="subtle" icon="plus" wire:click="$dispatch('create-contact', { role: 'vendor' })">
                {{ __('Add contact') }}
            </flux:button>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="assignContact">{{ __('Assign') }}</flux:button>
            </div>
        </div>
    </x-modal>

    <x-modal wire:model.self="showCancel" :title="__('Cancel task')">
        <div class="space-y-4">
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
        <div class="space-y-4">
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

    <livewire:contacts.edit />
</x-page>
