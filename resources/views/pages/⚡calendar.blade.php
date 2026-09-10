<?php

use App\Actions\Calendar\BuildMonthGrid;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\LdiTraining;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Calendar')] class extends Component {
    /**
     * The month on show, as Y-m, so a month has its own link to send.
     */
    #[Url]
    public string $month = '';

    /**
     * A training wears purple against the meeting's blue, and keeps the
     * dashed edge as well — those two hues are the pair a colourblind
     * reader is likeliest to confuse, and the edge settles it. The dash
     * also says the bar is not this page's to edit.
     */
    private const PLAN_CLASSES = 'border border-dashed border-purple-500 bg-purple-100 text-purple-900 dark:border-purple-300/50 dark:bg-purple-400/25 dark:text-purple-100';

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $title = '';

    public string $type = '';

    public string $date_start = '';

    public string $date_end = '';

    public string $time_start = '';

    public string $time_end = '';

    public string $location = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Activity::class);

        if ($this->month === '') {
            $this->month = CarbonImmutable::today()->format('Y-m');
        }
    }

    /**
     * The month asked for, or this one when the link carries nonsense.
     */
    #[Computed]
    public function shownMonth(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::today()->startOfMonth();
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->shownMonth->subMonth()->format('Y-m');

        $this->resetMonth();
    }

    public function nextMonth(): void
    {
        $this->month = $this->shownMonth->addMonth()->format('Y-m');

        $this->resetMonth();
    }

    public function today(): void
    {
        $this->month = CarbonImmutable::today()->format('Y-m');

        $this->resetMonth();
    }

    private function resetMonth(): void
    {
        unset($this->shownMonth, $this->activities, $this->plans, $this->weeks);
    }

    public function canManage(): bool
    {
        return auth()->user()->can('create', Activity::class);
    }

    /**
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function activities(): Collection
    {
        return Activity::query()
            ->overlapping($this->shownMonth, $this->shownMonth->endOfMonth())
            ->orderBy('date_start')
            ->orderBy('time_start')
            ->get();
    }

    /**
     * Trainings are planned elsewhere and only visit this page, so the
     * office sees one month rather than two half ones.
     *
     * @return Collection<int, LdiTraining>
     */
    #[Computed]
    public function plans(): Collection
    {
        return LdiTraining::query()
            ->whereDate('date_start', '<=', $this->shownMonth->endOfMonth())
            ->whereDate('date_end', '>=', $this->shownMonth)
            ->orderBy('date_start')
            ->get();
    }

    /**
     * The month as the weeks a calendar draws.
     *
     * Each week carries its seven days and the bars that cross it. A thing
     * running Monday to Thursday is one bar four days wide, not the same
     * chip repeated in four boxes, so the eye follows how long it lasts.
     *
     * @return list<array{days: list<array{day: int|null, date: CarbonImmutable|null}>, bars: list<array{key: string, kind: string, id: int, title: string, classes: string, column: int, span: int, lane: int, opensBefore: bool, runsOn: bool}>, lanes: int}>
     */
    #[Computed]
    public function weeks(): array
    {
        $weeks = [];

        foreach (app(BuildMonthGrid::class)->handle($this->shownMonth) as $week) {
            $dates = array_values(array_filter($week, fn (?CarbonImmutable $date): bool => $date !== null));

            $bars = $dates === [] ? [] : $this->barsAcross($dates[0], $dates[count($dates) - 1]);

            $weeks[] = [
                'days' => array_map(fn (?CarbonImmutable $date): array => [
                    'day' => $date?->day,
                    'date' => $date,
                ], $week),
                'bars' => $bars,
                'lanes' => $bars === [] ? 0 : max(array_column($bars, 'lane')) + 1,
            ];
        }

        return $weeks;
    }

    /**
     * Everything crossing one week, cut to that week and stacked so no two
     * bars land on the same line.
     *
     * @return list<array{key: string, kind: string, id: int, title: string, classes: string, column: int, span: int, lane: int, opensBefore: bool, runsOn: bool}>
     */
    private function barsAcross(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $bars = [];

        foreach ($this->activities as $activity) {
            $bars[] = $this->barFor(
                'activity',
                $activity->getKey(),
                $activity->title,
                $activity->type->chipClasses(),
                $activity->date_start,
                $activity->date_end,
                $weekStart,
                $weekEnd,
            );
        }

        foreach ($this->plans as $plan) {
            $bars[] = $this->barFor(
                'plan',
                $plan->getKey(),
                $plan->title,
                self::PLAN_CLASSES,
                $plan->date_start,
                $plan->date_end,
                $weekStart,
                $weekEnd,
            );
        }

        $bars = array_values(array_filter($bars));

        // Longest first from each starting day, so a week-long bar takes the
        // top line and the short ones tuck under it.
        usort($bars, fn (array $a, array $b): int => [$a['column'], -$a['span']] <=> [$b['column'], -$b['span']]);

        return $this->stack($bars);
    }

    /**
     * One bar, cut to the week it is being drawn on, or null when it does
     * not reach that week at all.
     *
     * @return array{key: string, kind: string, id: int, title: string, classes: string, column: int, span: int, lane: int, opensBefore: bool, runsOn: bool}|null
     */
    private function barFor(
        string $kind,
        int $id,
        string $title,
        string $classes,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
    ): ?array {
        if ($start->startOfDay()->gt($weekEnd) || $end->startOfDay()->lt($weekStart)) {
            return null;
        }

        $from = $start->startOfDay()->max($weekStart);
        $until = $end->startOfDay()->min($weekEnd);

        return [
            'key' => $kind.'-'.$id,
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'classes' => $classes,
            // CSS grid columns count from one, and the week starts on Sunday.
            'column' => $from->dayOfWeek + 1,
            'span' => (int) $from->diffInDays($until) + 1,
            'lane' => 0,
            'opensBefore' => $start->startOfDay()->lt($weekStart),
            'runsOn' => $end->startOfDay()->gt($weekEnd),
        ];
    }

    /**
     * Puts each bar on the first line where nothing is in its way.
     *
     * @param  list<array<string, mixed>>  $bars
     * @return list<array<string, mixed>>
     */
    private function stack(array $bars): array
    {
        /** @var list<list<bool>> $taken */
        $taken = [];

        foreach ($bars as $index => $bar) {
            $columns = range($bar['column'], $bar['column'] + $bar['span'] - 1);

            $lane = 0;

            while (true) {
                $taken[$lane] ??= [];

                $free = true;

                foreach ($columns as $column) {
                    if ($taken[$lane][$column] ?? false) {
                        $free = false;

                        break;
                    }
                }

                if ($free) {
                    break;
                }

                $lane++;
            }

            foreach ($columns as $column) {
                $taken[$lane][$column] = true;
            }

            $bars[$index]['lane'] = $lane;
        }

        return $bars;
    }

    /**
     * What each colour on the grid means, since a bar carries its title
     * rather than its kind.
     *
     * @return list<array{label: string, classes: string}>
     */
    #[Computed]
    public function legend(): array
    {
        $rows = [['label' => __('LDI training'), 'classes' => self::PLAN_CLASSES]];

        foreach (ActivityType::cases() as $case) {
            $rows[] = ['label' => $case->label(), 'classes' => $case->chipClasses()];
        }

        return $rows;
    }

    public string $viewingKind = '';

    public ?int $viewingId = null;

    /**
     * Clicking a bar shows what it is. The month grid has no room for a
     * time, a place and a description, so they live here.
     */
    public function show(string $kind, int $id): void
    {
        $this->viewingKind = $kind;
        $this->viewingId = $id;

        unset($this->viewing);

        Flux::modal('calendar-detail')->show();
    }

    #[Computed]
    public function viewing(): Activity|LdiTraining|null
    {
        if ($this->viewingId === null) {
            return null;
        }

        return $this->viewingKind === 'activity'
            ? Activity::find($this->viewingId)
            : LdiTraining::find($this->viewingId);
    }

    public function editViewed(): void
    {
        $id = $this->viewingId;

        Flux::modal('calendar-detail')->close();

        if ($id !== null && $this->viewingKind === 'activity') {
            $this->edit($id);
        }
    }

    public function removeViewed(): void
    {
        $id = $this->viewingId;

        Flux::modal('calendar-detail')->close();

        if ($id !== null && $this->viewingKind === 'activity') {
            $this->confirmDelete($id);
        }
    }

    public function create(?string $on = null): void
    {
        $this->authorize('create', Activity::class);

        $this->resetForm();

        $day = $on ?? $this->shownMonth->format('Y-m-d');

        $this->date_start = $day;
        $this->date_end = $day;

        Flux::modal('activity-form')->show();
    }

    public function edit(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);

        $this->authorize('update', $activity);

        $this->resetValidation();

        $this->editingId = $activity->getKey();
        $this->title = $activity->title;
        $this->type = $activity->type->value;
        $this->date_start = $activity->date_start->toDateString();
        $this->date_end = $activity->date_end->toDateString();
        $this->time_start = (string) $activity->time_start;
        $this->time_end = (string) $activity->time_end;
        $this->location = (string) $activity->location;
        $this->description = (string) $activity->description;

        Flux::modal('activity-form')->show();
    }

    public function save(): void
    {
        $this->authorize($this->editingId === null ? 'create' : 'update', $this->editingId === null
            ? Activity::class
            : Activity::findOrFail($this->editingId));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'time_start' => ['nullable', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i', 'after:time_start'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        Activity::updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'time_start' => $validated['time_start'] ?: null,
            'time_end' => $validated['time_end'] ?: null,
            'location' => $validated['location'] ?: null,
            'description' => $validated['description'] ?: null,
            'created_by' => $this->editingId === null ? auth()->id() : Activity::find($this->editingId)->created_by,
        ]);

        $this->resetForm();
        $this->resetMonth();

        Flux::modal('activity-form')->close();

        Flux::toast(variant: 'success', text: __('Saved to the calendar.'));
    }

    public function confirmDelete(int $activityId): void
    {
        $this->authorize('delete', Activity::findOrFail($activityId));

        $this->deletingId = $activityId;

        Flux::modal('activity-delete')->show();
    }

    #[Computed]
    public function deleting(): ?Activity
    {
        return $this->deletingId === null ? null : Activity::find($this->deletingId);
    }

    public function delete(): void
    {
        $activity = Activity::findOrFail($this->deletingId);

        $this->authorize('delete', $activity);

        $activity->delete();

        $this->deletingId = null;

        unset($this->deleting);
        $this->resetMonth();

        Flux::modal('activity-delete')->close();

        Flux::toast(variant: 'success', text: __('Taken off the calendar.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'title', 'type', 'date_start', 'date_end', 'time_start', 'time_end', 'location', 'description');
        $this->resetValidation();

        $this->type = ActivityType::Meeting->value;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <flux:heading size="xl">{{ __('Calendar') }}</flux:heading>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" square
                wire:click="previousMonth" :aria-label="__('Previous month')" />

            <div class="min-w-40 text-center">
                <flux:heading size="lg">{{ $this->shownMonth->format('F Y') }}</flux:heading>
            </div>

            <flux:button size="sm" variant="ghost" icon="chevron-right" square
                wire:click="nextMonth" :aria-label="__('Next month')" />

            <flux:button size="sm" variant="ghost" wire:click="today">{{ __('Today') }}</flux:button>

            @if ($this->canManage())
                <flux:button variant="primary" wire:click="create">{{ __('Add activity') }}</flux:button>
            @endif
        </div>
    </div>

    {{-- Seven days wide with room to scroll, because a title cut to a
         seventh of a phone is not a title. --}}
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <flux:card class="min-w-3xl space-y-2">
            <div class="grid grid-cols-7 gap-1 text-center text-xs text-zinc-600 dark:text-zinc-300">
                @foreach ([__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')] as $weekday)
                    <div>{{ $weekday }}</div>
                @endforeach
            </div>

            @foreach ($this->weeks as $week)
                {{-- One grid per week: the day boxes fill every row so a bar
                     can be laid over them, spanning as many days as it runs. --}}
                <div class="grid grid-cols-7 gap-1"
                    style="grid-template-rows: auto repeat({{ max($week['lanes'], 1) }}, auto);">
                    @foreach ($week['days'] as $index => $cell)
                        <div style="grid-column: {{ $index + 1 }}; grid-row: 1 / -1;"
                            @class([
                                'min-h-24 rounded-md',
                                'bg-zinc-50 dark:bg-white/2' => $cell['day'] === null,
                                'border border-zinc-200 dark:border-white/10' => $cell['day'] !== null && ! $cell['date']->isToday(),
                                'border border-[var(--color-accent)] bg-zinc-50 dark:bg-white/5' => $cell['day'] !== null && $cell['date']->isToday(),
                            ])></div>
                    @endforeach

                    @foreach ($week['days'] as $index => $cell)
                        @if ($cell['day'] !== null)
                            <div class="flex items-center justify-between px-1 pt-1"
                                style="grid-column: {{ $index + 1 }}; grid-row: 1;">
                                <span class="text-xs tabular-nums">{{ $cell['day'] }}</span>

                                @if ($this->canManage())
                                    {{-- A plain button, because a flux:button here
                                         would be larger than the cell it sits in. --}}
                                    <button type="button"
                                        wire:click="create('{{ $cell['date']->toDateString() }}')"
                                        class="cursor-pointer px-1 text-xs leading-none text-zinc-500 hover:text-[var(--color-accent-content)] dark:text-zinc-400"
                                        aria-label="{{ __('Add an activity on :date', ['date' => $cell['date']->format('F j')]) }}">+</button>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    @foreach ($week['bars'] as $bar)
                        <button type="button" wire:key="{{ $loop->parent->index }}-{{ $bar['key'] }}"
                            wire:click="show('{{ $bar['kind'] }}', {{ $bar['id'] }})"
                            style="grid-column: {{ $bar['column'] }} / span {{ $bar['span'] }}; grid-row: {{ $bar['lane'] + 2 }};"
                            @class([
                                'mx-0.5 block cursor-pointer truncate px-1.5 py-0.5 text-left text-[11px] leading-tight',
                                $bar['classes'],
                                'rounded-s-md' => ! $bar['opensBefore'],
                                'rounded-e-md' => ! $bar['runsOn'],
                            ])
                            title="{{ $bar['title'] }}">{{ $bar['opensBefore'] ? '◀ ' : '' }}{{ $bar['title'] }}</button>
                    @endforeach
                </div>
            @endforeach

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-200 pt-3 dark:border-white/10">
                @foreach ($this->legend as $entry)
                    <div class="flex items-center gap-1.5">
                        <span class="size-3 rounded-sm {{ $entry['classes'] }}" aria-hidden="true"></span>
                        <flux:text size="sm">{{ $entry['label'] }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <flux:modal name="calendar-detail" class="md:w-xl">
        <div class="space-y-6">
            @if ($this->viewing)
                <div class="space-y-2">
                    <flux:heading size="lg">{{ $this->viewing->title }}</flux:heading>

                    @if ($this->viewing instanceof Activity)
                        <flux:badge size="sm" :color="$this->viewing->type->color()">
                            {{ $this->viewing->type->label() }}
                        </flux:badge>
                    @else
                        <flux:badge size="sm" color="purple">{{ __('LDI training') }}</flux:badge>
                    @endif
                </div>

                <div class="space-y-1">
                    <flux:text size="sm">{{ $this->viewing->inclusive_dates }}</flux:text>

                    @if ($this->viewing instanceof Activity && $this->viewing->timeRange())
                        <flux:text size="sm">{{ $this->viewing->timeRange() }}</flux:text>
                    @endif

                    @if ($this->viewing->location)
                        <flux:text size="sm">{{ $this->viewing->location }}</flux:text>
                    @endif

                    @if ($this->viewing instanceof Activity && $this->viewing->description)
                        <flux:text size="sm">{{ $this->viewing->description }}</flux:text>
                    @endif

                    @if ($this->viewing instanceof LdiTraining)
                        <flux:text size="sm">
                            {{ __('Conducted by :facilitator', ['facilitator' => $this->viewing->facilitator]) }}
                        </flux:text>
                    @endif
                </div>
            @endif

            <div class="flex gap-2">
                @if ($this->viewing instanceof LdiTraining && auth()->user()->can('view', $this->viewing))
                    <flux:button size="sm" variant="ghost" :href="route('ldi.show', $this->viewing)" wire:navigate>
                        {{ __('Open the plan') }}
                    </flux:button>
                @endif

                @if ($this->viewing instanceof Activity && $this->canManage())
                    <flux:button size="sm" variant="ghost" wire:click="editViewed">{{ __('Edit') }}</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="removeViewed">{{ __('Remove') }}</flux:button>
                @endif

                <flux:spacer />

                <flux:modal.close>
                    <flux:button size="sm" variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- A reader is sent no form at all, rather than one they would be
         refused on submitting. --}}
    @if ($this->canManage())
        <flux:modal name="activity-form" class="md:w-5xl md:max-w-[calc(100vw-4rem)]">
            <form wire:submit="save" class="space-y-6">
                <flux:heading size="lg">
                    {{ $editingId === null ? __('Add activity') : __('Edit activity') }}
                </flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input class="md:col-span-2" wire:model="title" :label="__('Title')" required />

                    <flux:select wire:model="type" :label="__('Type')" required>
                        @foreach (ActivityType::cases() as $case)
                            <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="location" :label="__('Location')" />

                    <flux:input wire:model="date_start" :label="__('From')" type="date" required />
                    <flux:input wire:model="date_end" :label="__('To')" type="date" required />

                    <flux:input wire:model="time_start" :label="__('Start time')" type="time"
                        :description="__('Leave both empty for a whole-day activity.')" />
                    <flux:input wire:model="time_end" :label="__('End time')" type="time" />

                    <flux:textarea class="md:col-span-2" wire:model="description" :label="__('Description')" rows="3" />
                </div>

                <div class="flex gap-2">
                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary">
                        {{ $editingId === null ? __('Add') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="activity-delete" class="md:w-5xl md:max-w-[calc(100vw-4rem)]">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Take this off the calendar?') }}</flux:heading>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-3">
                        @if ($this->deleting)
                            <div>
                                <flux:text size="sm">{{ __('Title') }}</flux:text>
                                <flux:heading>{{ $this->deleting->title }}</flux:heading>
                            </div>
                            <div>
                                <flux:text size="sm">{{ __('When') }}</flux:text>
                                <flux:heading>{{ $this->deleting->inclusive_dates }}</flux:heading>
                            </div>
                    @endif
                </div>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('It is removed for everybody who reads the calendar. This cannot be undone.') }}
                </flux:callout>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="delete">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif
</div>
