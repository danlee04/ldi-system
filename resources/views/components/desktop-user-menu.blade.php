{{-- The foot of the nav: who is signed in, the one page about them, and
     the way out. It was a dropdown; three plain rows say the same thing
     without asking anybody to open anything. --}}
{{-- The caller supplies `hidden lg:flex`, so the direction and the gap are
     all this adds: `flex` here would fight the `hidden` that keeps the foot
     off a phone, and one of the two would win at random. --}}
<div {{ $attributes->class('w-full flex-col gap-1') }}>
    <div class="flex items-center gap-2 px-2 py-1.5 in-data-flux-sidebar-collapsed-desktop:px-0 in-data-flux-sidebar-collapsed-desktop:justify-center">
        <livewire:profile-avatar :plain="true" :name="auth()->user()->employee?->personal_name ?? auth()->user()->name" />

        <div class="min-w-0 flex-1 truncate text-sm font-medium text-white in-data-flux-sidebar-collapsed-desktop:hidden"
            title="{{ auth()->user()->employee?->personal_name ?? auth()->user()->name }}">
            {{ auth()->user()->employee?->personal_name ?? auth()->user()->name }}
        </div>
    </div>

    @if (auth()->user()->employee !== null)
        <flux:sidebar.item icon="user-circle" :href="route('my-profile')"
            :current="request()->routeIs('my-profile')" wire:navigate>
            {{ __('My profile') }}
        </flux:sidebar.item>
    @endif

    {{-- Who may sign in is the administrator's own business, not a setting
         about the office, so it sits with their name rather than in Setup. --}}
    @if (auth()->user()->role === App\Enums\UserRole::Admin)
        <flux:sidebar.item icon="key" :href="route('setup.users')"
            :current="request()->routeIs('setup.users')" wire:navigate>
            {{ __('User accounts') }}
        </flux:sidebar.item>
    @endif

    {{-- Flux wraps every sidebar item in a <ui-tooltip>. The one above is a
         direct child of this column and stretches to it; this one sits
         inside the form, where it shrinks to the words unless it is told
         to fill the line. --}}
    <form method="POST" action="{{ route('logout') }}" class="w-full [&>ui-tooltip]:block">
        @csrf

        <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
            class="w-full cursor-pointer" data-test="logout-button">
            {{ __('Log out') }}
        </flux:sidebar.item>
    </form>
</div>
