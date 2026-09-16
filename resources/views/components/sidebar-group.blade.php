@props(['heading'])

{{-- Flux's own sidebar group hides itself and everything inside it when the
     sidebar folds, which leaves the nav showing only the items that sit
     outside a group. This is the same shape without that: the heading goes
     when there is no room for words, and the items stay as their icons. --}}
<div {{ $attributes->class('flex flex-col') }}>
    <div class="px-3 py-2 in-data-flux-sidebar-collapsed-desktop:hidden">
        {{-- Smaller than the items under it, so a heading is never mistaken
             for one of them. It stays at 80% white: at 12px anything fainter
             drops under 4.5:1 on the nav's blue. --}}
        <div class="text-xs leading-none font-medium tracking-wide text-white/80">{{ $heading }}</div>
    </div>

    {{ $slot }}
</div>
