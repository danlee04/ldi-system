@props([
    'title',
    'description' => null,
])

{{-- Left, because everything under it is left. --}}
<div class="flex w-full flex-col">
    <flux:heading size="xl" level="1">{{ $title }}</flux:heading>

    @if ($description)
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
