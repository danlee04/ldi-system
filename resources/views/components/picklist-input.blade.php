@props([
    'label',
    'options' => [],
    'description' => null,
])

@php
    // Suggestions, not a closed list. The recurring choices are offered but
    // an unlisted partner or budget source can always be typed in, which is
    // what the old system's "Other" box was for.
    $listId = 'picklist-'.Str::random(8);
@endphp

<div>
    <flux:input :label="$label" :description="$description" :list="$listId" {{ $attributes }} />

    <datalist id="{{ $listId }}">
        @foreach ($options as $option)
            <option value="{{ $option }}"></option>
        @endforeach
    </datalist>
</div>
