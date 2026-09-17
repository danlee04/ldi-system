@props([
    'sidebar' => false,
])

@php
    $brand = $sidebar ? 'flux:sidebar.brand' : 'flux:brand';
@endphp

{{-- No tinted box behind it. The seal is already a shape with its own
     colours, and a coloured square around it fights both. --}}
@if ($sidebar)
    {{-- White, and padded, because the seal is a transparent PNG: on the
         nav's blue its dark green sits straight on dark blue. The padding
         is what shows as the border — a ring on its own would be a hoop
         around a shape still lost in the background. --}}
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-full bg-white p-1">
            <x-app-logo-icon class="size-full" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:brand>
@endif
