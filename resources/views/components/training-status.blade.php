@props(['record'])

@php($status = $record->status)

@if ($status === App\Enums\TrainingStatus::Approved)
    <flux:badge color="green">{{ __('Approved') }}</flux:badge>
@elseif ($status === App\Enums\TrainingStatus::Rejected)
    <flux:badge color="red">{{ __('Rejected') }}</flux:badge>
@elseif ($record->current_level === null)
    <flux:badge color="amber">{{ __('No approver') }}</flux:badge>
@else
    <flux:badge color="zinc">
        {{ __('Waiting for :level', ['level' => $record->current_level->label()]) }}
    </flux:badge>
@endif
