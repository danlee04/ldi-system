@props(['employees', 'plans', 'coverage', 'funding', 'spend', 'year'])

@php
    // Four figures, one line of support each. The breakdowns they used to
    // carry are panels of their own further down the page — a card with
    // twenty rows under the number is a table wearing a number's clothes.
    $cards = [
        [
            'icon' => 'users',
            'label' => __('Employees'),
            'value' => number_format($employees['total']),
            'support' => trans_choice('across :count division|across :count divisions', count($employees['rows']), [
                'count' => count($employees['rows']),
            ]),
        ],
        [
            'icon' => 'academic-cap',
            'label' => __('LDI trainings in :year', ['year' => $year]),
            'value' => number_format($plans['total']),
            'support' => $plans['rows'] === []
                ? __('none planned yet')
                : __('most are :type', ['type' => mb_strtolower($plans['rows'][0]['label'])]),
        ],
        [
            'icon' => 'check-badge',
            'label' => __('Trained in :year', ['year' => $year]),
            'value' => $coverage['percentage'].'%',
            'support' => __(':covered of :employees employees', [
                'covered' => number_format($coverage['covered']),
                'employees' => number_format($coverage['employees']),
            ]),
        ],
        [
            'icon' => 'banknotes',
            'label' => __('Spent in :year', ['year' => $year]),
            'value' => number_format($spend['total'], 2),
            'support' => __(':amount funded by HR', ['amount' => number_format($funding['hr'], 2)]),
        ],
    ];
@endphp

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

                <flux:text size="sm">{{ $card['support'] }}</flux:text>
            </div>
        </flux:card>
    @endforeach
</div>
