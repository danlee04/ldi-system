@props(['cards'])

@php
    /**
     * The surfaces a figure card can wear, and the ink that reads on each.
     *
     * Measured against white, or against zinc-950 for amber, and every one
     * clears 4.5:1 in both themes — the surfaces do not change under dark,
     * so a single measurement holds:
     *
     *   blue   #2563eb   5.17:1     teal   #0f766e   5.47:1
     *   violet #7c3aed   5.70:1     pink   #db2777   4.60:1
     *   amber  #fbbf24  11.70:1 on zinc-950
     *
     * Deliberately no green and no red: this app spends those on approved
     * and rejected, and a green card holding a headcount would be claiming
     * something it does not mean. Amber keeps its meaning too — it is what
     * the nav badge uses for "waiting on you", so a card only turns amber
     * when something really is.
     *
     * Blue and violet are the weakest pair for a colourblind reader. That
     * is liveable here and only here: every card carries its own icon and
     * its own label, so the colour is never what tells them apart. Do not
     * carry this set into a chart, where the colour would be the only cue.
     */
    $tones = [
        'blue' => ['bg-brand-primary text-white', 'bg-white/20'],
        'teal' => ['bg-teal-700 text-white', 'bg-white/20'],
        'violet' => ['bg-violet-600 text-white', 'bg-white/20'],
        'pink' => ['bg-pink-600 text-white', 'bg-white/20'],
        'amber' => ['bg-amber-400 text-zinc-950', 'bg-zinc-950/10'],
    ];
@endphp

{{-- A row of figures, one line of support each. Each card is
     ['icon', 'label', 'value', 'support'] and may carry an 'href', which
     turns the support line into the way to act on the figure, and a
     'tone' naming one of the surfaces above.

     Plain divs, not flux:card: the card sets its own light surface with
     zero-specificity classes, and a background passed against it loses
     silently. Same reason the link is a plain anchor rather than
     flux:link, whose accent blue would vanish into these surfaces.

     Size and weight carry the hierarchy, never a faded white: white at
     90% on the brand blue measures 4.49:1, a hair under the floor. --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        @php([$surface, $plate] = $tones[$card['tone'] ?? 'blue'] ?? $tones['blue'])

        <div class="space-y-3 rounded-xl p-5 {{ $surface }}">
            <div class="flex min-h-9 items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $plate }}">
                    <flux:icon :icon="$card['icon']" variant="mini" />
                </span>

                <span class="min-w-0 text-sm leading-tight">{{ $card['label'] }}</span>
            </div>

            <div>
                <div class="text-2xl font-semibold tabular-nums">{{ $card['value'] }}</div>

                @if (isset($card['href']))
                    <a href="{{ $card['href'] }}" wire:navigate
                        class="text-sm underline underline-offset-4 hover:no-underline">
                        {{ $card['support'] }}
                    </a>
                @else
                    <div class="text-sm">{{ $card['support'] }}</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
