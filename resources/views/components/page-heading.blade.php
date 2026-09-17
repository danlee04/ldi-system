@props(['icon'])

{{-- The badge the dashboard opens with, so every page is titled the same
     way. The icon is the one the page carries in the sidebar, so the nav
     and the heading never disagree about what a page is.

     flux:icon takes the name as a prop and delegates to icon.<name>, which
     is what lets this be passed in rather than written out per page. --}}
<div {{ $attributes->class('flex items-center gap-3') }}>
    <span
        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
        <flux:icon :icon="$icon" variant="mini" />
    </span>

    <flux:heading size="xl">{{ $slot }}</flux:heading>
</div>
