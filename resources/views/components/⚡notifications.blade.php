<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The bell.
 *
 * An approver used to learn of a submission only by opening the approvals
 * queue, and an employee learned of a decision only by going to look. This
 * is the one place either of them is told.
 */
new class extends Component {
    /**
     * Enough to see at a glance without turning the bell into a page.
     */
    public const SHOWN = 8;

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function unread(): Collection
    {
        return auth()->user()->unreadNotifications()->take(self::SHOWN)->get();
    }

    #[Computed]
    public function count(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * Opens what the notification is about, and marks it read on the way.
     */
    public function open(string $id): mixed
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();

        unset($this->unread, $this->count);

        return $this->redirect(route($notification->data['route'] ?? 'dashboard'), navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        unset($this->unread, $this->count);
    }
}; ?>

<flux:dropdown position="bottom" align="end" wire:poll.60s>
    {{-- The badge is positioned out of flow, so the button stays square. --}}
    <flux:button variant="subtle" size="sm" icon="bell" square class="relative">
        @if ($this->count > 0)
            <flux:badge color="red" size="sm" class="absolute -end-1 -top-1">
                {{ $this->count > 99 ? '99+' : $this->count }}
            </flux:badge>
        @endif
    </flux:button>

    <flux:menu class="w-80">
        <div class="flex items-center justify-between px-2 py-1.5">
            <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>

            @if ($this->count > 0)
                <button type="button" wire:click="markAllRead"
                    class="cursor-pointer text-xs text-[var(--color-accent-content)] hover:opacity-70">
                    {{ __('Mark all read') }}
                </button>
            @endif
        </div>

        <flux:menu.separator />

        @forelse ($this->unread as $notification)
            <button type="button" wire:key="notification-{{ $notification->id }}"
                wire:click="open('{{ $notification->id }}')"
                class="block w-full cursor-pointer px-2 py-2 text-left hover:bg-zinc-100 dark:hover:bg-white/5">
                <div class="truncate text-sm font-medium">
                    @if (($notification->data['kind'] ?? '') === 'awaiting')
                        {{ __('Awaiting your decision') }}
                    @elseif (($notification->data['decision'] ?? '') === 'rejected')
                        {{ __('Your training was rejected') }}
                    @else
                        {{ __('Your training was approved') }}
                    @endif
                </div>

                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $notification->data['title'] ?? '' }}
                    @if (filled($notification->data['employee'] ?? ''))
                        — {{ $notification->data['employee'] }}
                    @endif
                </div>

                @if (filled($notification->data['reason'] ?? ''))
                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $notification->data['reason'] }}
                    </div>
                @endif

                <div class="text-xs text-zinc-400 dark:text-zinc-500">
                    {{ $notification->created_at->diffForHumans() }}
                </div>
            </button>
        @empty
            <div class="px-2 py-6 text-center">
                <flux:text size="sm">{{ __('Nothing new.') }}</flux:text>
            </div>
        @endforelse
    </flux:menu>
</flux:dropdown>
