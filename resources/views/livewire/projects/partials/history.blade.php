<flux:card body="divided" wire:poll.5s.visible>
    <flux:card.header>
        <flux:heading size="sm">{{ __('Project histories') }}</flux:heading>
    </flux:card.header>

    <flux:card.body class="max-h-96 overflow-y-auto">
        @if ($this->timeline->isEmpty())
            <flux:text class="text-sm">{{ __('No histories yet.') }}</flux:text>
        @else
            <x-ui.timeline>
                @foreach ($this->timeline as $log)
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
