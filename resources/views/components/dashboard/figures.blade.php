@props(['cards'])

{{-- A row of figures, one line of support each. Each card is
     ['icon', 'label', 'value', 'support'] and may carry an 'href', which
     turns the support line into the way to act on the figure. --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        <flux:card class="space-y-3">
            <div class="flex min-h-9 items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
                    <flux:icon :icon="$card['icon']" variant="mini" />
                </span>

                <flux:text size="sm" class="min-w-0 leading-tight">{{ $card['label'] }}</flux:text>
            </div>

            <div>
                <flux:heading size="xl" class="tabular-nums">{{ $card['value'] }}</flux:heading>

                @if (isset($card['href']))
                    <flux:link :href="$card['href']" wire:navigate class="text-sm">{{ $card['support'] }}</flux:link>
                @else
                    <flux:text size="sm">{{ $card['support'] }}</flux:text>
                @endif
            </div>
        </flux:card>
    @endforeach
</div>
