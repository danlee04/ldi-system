@props(['lines'])

<flux:card class="space-y-3">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ __('Eligibility alerts') }}</flux:heading>
        <flux:text size="sm">{{ trans_choice(':count person|:count people', $lines->count(), ['count' => $lines->count()]) }}</flux:text>
    </div>

    @if ($lines->isEmpty())
        <flux:text size="sm">{{ __('Nothing lapses within the year.') }}</flux:text>
    @else
        <div class="divide-y divide-zinc-200 dark:divide-white/10">
            @foreach ($lines->take(8) as $line)
                <div class="flex flex-wrap items-start justify-between gap-2 py-2 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <div class="truncate text-sm" title="{{ $line->employee?->full_name }}">
                            {{ $line->employee?->listing_name ?? '—' }}
                        </div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $line->name() }}">
                            {{ $line->name() }}
                        </div>
                    </div>

                    <x-eligibility-expiry :date="$line->date_of_validity" />
                </div>
            @endforeach
        </div>

        @if ($lines->count() > 8)
            <flux:text size="sm">
                {{ __(':count more not shown.', ['count' => $lines->count() - 8]) }}
            </flux:text>
        @endif
    @endif
</flux:card>
