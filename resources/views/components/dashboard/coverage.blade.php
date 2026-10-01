@props(['rows', 'year', 'heading' => null])

<flux:card class="space-y-5">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="lg" class="font-semibold">
                {{ $heading ?? __('Training coverage by division') }}
            </flux:heading>

            <flux:text size="sm" class="mt-1">
                {{ __('Employees who completed training during :year', ['year' => $year]) }}
            </flux:text>
        </div>

        <span
            class="shrink-0 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
            {{ $year }}
        </span>
    </div>

    @forelse ($rows as $row)
        @php
            $percentage = min(100, max(0, (float) $row['percentage']));
        @endphp

        <div class="space-y-2.5">
            {{-- Label --}}
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium">
                        {{ $row['division'] }}
                    </div>
                </div>

                <div class="shrink-0 text-right">
                    <span class="text-sm font-semibold tabular-nums">
                        {{ $row['percentage'] }}%
                    </span>

                    <span class="ml-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $row['covered'] }}/{{ $row['employees'] }}
                    </span>
                </div>
            </div>

            {{-- Progress --}}
            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10" role="progressbar"
                aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"
                aria-label="{{ $row['division'] }}">
                <div class="h-full rounded-full bg-brand-primary transition-all duration-500"
                    style="width: {{ $percentage }}%"></div>
            </div>
        </div>
    @empty
        <div
            class="flex min-h-32 items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-white/10">
            <flux:text size="sm">
                {{ __('No division has anybody in it yet.') }}
            </flux:text>
        </div>
    @endforelse
</flux:card>
