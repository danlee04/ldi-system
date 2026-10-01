@props(['lines'])

<flux:card class="space-y-5">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="lg" class="font-semibold">
                {{ __('Eligibility alerts') }}
            </flux:heading>

            <flux:text size="sm" class="mt-1">
                {{ __('Upcoming eligibility expirations') }}
            </flux:text>
        </div>

        <span
            class="rounded-full px-2.5 py-1 text-xs font-semibold
                {{ $lines->isEmpty()
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400' }}">
            {{ $lines->count() }}
        </span>
    </div>

    @if ($lines->isEmpty())
        <div
            class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-4 dark:border-emerald-400/20 dark:bg-emerald-400/5">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400">
                <flux:icon name="check" variant="mini" />
            </span>

            <div>
                <div class="text-sm font-medium text-emerald-900 dark:text-emerald-300">
                    {{ __('Everything looks good') }}
                </div>

                <div class="text-xs text-emerald-700/80 dark:text-emerald-400/80">
                    {{ __('Nothing lapses within the year.') }}
                </div>
            </div>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($lines->take(8) as $line)
                <div
                    class="group flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-3 transition-colors hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/[0.03]">
                    {{-- Status icon --}}
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">
                        <flux:icon name="exclamation-triangle" variant="mini" />
                    </span>

                    {{-- Employee --}}
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium" title="{{ $line->employee?->full_name }}">
                            {{ $line->employee?->listing_name ?? '—' }}
                        </div>

                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $line->name() }}">
                            {{ $line->name() }}
                        </div>
                    </div>

                    {{-- Expiry --}}
                    <div class="shrink-0 text-right">
                        <x-eligibility-expiry :date="$line->date_of_validity" />
                    </div>
                </div>
            @endforeach
        </div>

        @if ($lines->count() > 8)
            <div class="border-t border-zinc-200 pt-3 dark:border-white/10">
                <flux:text size="sm">
                    {{ __(':count more not shown.', ['count' => $lines->count() - 8]) }}
                </flux:text>
            </div>
        @endif
    @endif
</flux:card>
