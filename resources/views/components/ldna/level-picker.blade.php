@props(['competency', 'disabled' => false])

{{-- The four levels as cards, each carrying what the dictionary says the
     competency looks like there, so a rater picks a description rather
     than a number. Flux puts `flex gap-3` on the group itself, so the
     layout here only sets the direction — a grid class would fight it. --}}
<flux:radio.group {{ $attributes }} variant="cards" class="flex-col xl:flex-row">
    @foreach (\App\Enums\ProficiencyLevel::cases() as $level)
        <flux:radio :value="$level->value" :label="$level->label()"
            :description="$competency->indicatorFor($level)" :disabled="$disabled" />
    @endforeach
</flux:radio.group>
