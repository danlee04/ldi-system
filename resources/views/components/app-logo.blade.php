@props([
    'sidebar' => false,
])

@php
    $brand = $sidebar ? 'flux:sidebar.brand' : 'flux:brand';
@endphp

{{-- No tinted box behind it. The seal is already a shape with its own
     colours, and a coloured square around it fights both. --}}
@if ($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:brand>
@endif
