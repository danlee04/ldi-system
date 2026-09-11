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

<x-dashboard.figures :cards="$cards" />
