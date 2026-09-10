@props([
    'title',
    'description' => null,
])

{{-- Centred under the seals, which are centred. --}}
<div class="flex w-full flex-col text-center">
    <flux:heading size="xl" level="1">{{ $title }}</flux:heading>

    @if ($description)
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
