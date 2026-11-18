<x-page :heading="__('Project kinds')">
    <x-slot:subheading>
        <flux:subheading>{{ __('Reusable task trees for new projects') }}</flux:subheading>
    </x-slot:subheading>

    <x-slot:actions>
        @if ($this->isManager)
            <flux:button variant="primary" icon="plus" :href="route('project-kinds.create')" wire:navigate>
                {{ __('Add project kind') }}
            </flux:button>
        @endif
    </x-slot:actions>

    <div class="overflow-x-auto">
    <flux:table :paginate="$kinds">
        <flux:table.columns>
            <flux:table.column class="w-9/12">{{ __('Title') }}</flux:table.column>
            <flux:table.column class="w-2/12">{{ __('No. of tasks') }}</flux:table.column>
            <flux:table.column class="w-1/12">{{ __('Created at') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($kinds as $kind)
                <flux:table.row :key="$kind->id">
                    <flux:table.cell>
                        <flux:link :href="route('project-kinds.edit', ['kind' => $kind->id])" wire:navigate>
                            {{ $kind->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $kind->tasks_count }}</flux:table.cell>
                    <flux:table.cell><x-local-time :date="$kind->created_at" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text>{{ __('No project kinds yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </div>
</x-page>
