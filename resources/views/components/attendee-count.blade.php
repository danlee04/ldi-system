@props(['actual', 'target' => null])

@if ($target === null)
    <flux:badge color="zinc">{{ $actual }}</flux:badge>
@elseif ($actual >= $target)
    <flux:badge color="green">{{ $actual }} / {{ $target }}</flux:badge>
@elseif ($actual === 0)
    <flux:badge color="red">{{ $actual }} / {{ $target }}</flux:badge>
@else
    <flux:badge color="amber">{{ $actual }} / {{ $target }}</flux:badge>
@endif
