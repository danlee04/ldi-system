@props(['heading'])

{{-- Flux's own sidebar group hides itself and everything inside it when the
     sidebar folds, which leaves the nav showing only the items that sit
     outside a group. This is the same shape without that: the heading goes
     when there is no room for words, and the items stay as their icons. --}}
<div {{ $attributes->class('flex flex-col') }}>
    <div class="px-3 py-2 in-data-flux-sidebar-collapsed-desktop:hidden">
        <div class="text-sm leading-none font-medium text-zinc-500 dark:text-zinc-400">{{ $heading }}</div>
    </div>

    {{ $slot }}
</div>
