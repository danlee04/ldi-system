<?php

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The face on a profile button.
 *
 * It is a component of its own only so that saving a photograph on My
 * profile shows up at once. The sidebar and the header are plain Blade and
 * would otherwise keep the old initials until the next full page load.
 *
 * Two of these are on a page: the sidebar's, which carries a name, and the
 * mobile header's, which is the button alone.
 */
new class extends Component {
    public ?string $name = null;

    /**
     * The sidebar's own profile button, which is shaped differently from
     * the plain one in the mobile header.
     */
    public bool $sidebar = false;

    #[On('photo-updated')]
    public function refresh(): void
    {
        // Livewire re-renders after handling an event; the view reads the
        // employee again, so there is nothing to do here.
    }

    public function with(): array
    {
        return ['photo' => auth()->user()->employee?->photoUrl()];
    }
}; ?>

@php($initials = $photo === null ? auth()->user()->initials() : null)

{{-- No wrapper around these. flux:sidebar.profile widens itself with
     `[ui-dropdown>&]:w-full`, which only matches while the button is a
     direct child of the dropdown — wrap it and the button sizes to the
     name instead, pushing the sidebar wider than the screen. --}}
@if ($sidebar)
    <flux:sidebar.profile :avatar="$photo" :initials="$initials" :name="$name"
        icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" />
@else
    <flux:profile :avatar="$photo" :initials="$initials" icon-trailing="chevron-down" />
@endif
