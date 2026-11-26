<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Shown once per account: the 'confidential' middleware sends everyone
 * here until they tick the box, and never again after.
 */
new #[Layout('layouts::auth'), Title('Statement of Confidentiality')] class extends Component {
    public bool $agreed = false;

    public function mount(): void
    {
        if (Auth::user()->confidentiality_accepted_at !== null) {
            $this->redirectRoute('dashboard');
        }
    }

    public function agree(): void
    {
        $this->validate(
            ['agreed' => 'accepted'],
            ['agreed.accepted' => __('Check the box to agree before you continue.')],
        );

        // Not fillable, like email_verified_at: only this page may set it.
        Auth::user()->forceFill(['confidentiality_accepted_at' => now()])->save();

        $this->redirectIntended(route('dashboard'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Statement of Confidentiality')"
        :description="__('Read it and agree once before you go on.')" />

    {{-- The statement scrolls in its own box, sized to what the screen has
         left (about 32rem goes to the seals, heading, box and buttons), so
         the checkbox and Continue stay in view. --}}
    <x-confidentiality-statement tabindex="0" role="region" :aria-label="__('Statement of Confidentiality')"
        class="max-h-[calc(100dvh-32rem)] min-h-40 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" />

    <form wire:submit="agree" class="flex flex-col gap-6">
        <flux:checkbox wire:model="agreed" name="agreed"
            :label="__('I have read the Statement of Confidentiality and I agree to keep employee information confidential.')" />

        <flux:button type="submit" variant="primary" class="w-full" data-test="agree-button">
            {{ __('Continue') }}
        </flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <flux:button variant="ghost" type="submit" class="cursor-pointer text-sm" data-test="logout-button">
            {{ __('Log out') }}
        </flux:button>
    </form>
</div>
