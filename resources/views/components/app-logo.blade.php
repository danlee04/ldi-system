@props([
    'sidebar' => false,
])

@php
    $brand = $sidebar ? 'flux:sidebar.brand' : 'flux:brand';
@endphp

{{-- No tinted box behind it. The seal is already a shape with its own
     colours, and a coloured square around it fights both. --}}
@if ($sidebar)
    {{-- White because the seal is a transparent PNG: on the nav's blue its
         dark green sits straight on dark blue. Barely any padding — the
         artwork carries its own margin inside the file, and anything more
         here lands on top of that and shrinks the seal to a speck. --}}
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-full bg-white p-px">
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
