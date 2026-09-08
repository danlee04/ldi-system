@props(['date'])

@if ($date === null)
    <flux:badge color="zinc">{{ __('No expiry') }}</flux:badge>
@elseif ($date->isPast())
    <flux:badge color="red">{{ $date->format('d M Y') }}</flux:badge>
@elseif ($date->lessThanOrEqualTo(today()->addYear()))
    <flux:badge color="amber">{{ $date->format('d M Y') }}</flux:badge>
@else
    <flux:badge color="green">{{ $date->format('d M Y') }}</flux:badge>
@endif
