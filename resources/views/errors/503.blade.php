@php
    // A Livewire update posts to an address of its own, so the page to try
    // again is the one that sent it, not the address that failed.
    $again = request()->hasHeader('X-Livewire')
        ? url()->previous(route('home'))
        : request()->fullUrl();
@endphp

<x-layouts::error code="503" :title="__('Down for maintenance')" :action-label="__('Try again')" :action-href="$again" :back="false">
    {{ __('The system is being updated and will be back shortly.') }}
</x-layouts::error>
