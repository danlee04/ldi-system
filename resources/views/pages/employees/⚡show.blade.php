<?php

use App\Models\Employee;
use App\Models\TrainingRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee')] class extends Component {
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->authorize('view', $employee);

        $this->employee = $employee->load(['section.division', 'position']);
    }

    /**
     * The whole training history, newest first.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function records(): Collection
    {
        return $this->employee->trainingRecords()->orderByDesc('date_end')->get();
    }

    #[On('training-saved')]
    public function refresh(): void
    {
        unset($this->records);
    }
}; ?>

<div class="space-y-6">

    {{-- Back navigation --}}
    <div>
        <flux:button size="sm" variant="ghost" icon="chevron-left" :href="route('employees.index')" wire:navigate>
            {{ __('Employees') }}
        </flux:button>
    </div>

    {{-- Employee header --}}
    <flux:card class="relative overflow-hidden p-0">

        {{-- Accent background --}}
        {{-- inset-0, not a 6rem band: the card is taller than the band on a
             narrow screen, and the tint used to stop in the middle of it with
             a visible seam across the card. --}}
        <div
            class="absolute inset-0 bg-linear-to-r from-[var(--color-accent)]/15 via-[var(--color-accent)]/5 to-transparent">
        </div>

        <div class="relative p-5 sm:p-6">

            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

                {{-- Employee identity --}}
                <div class="flex min-w-0 items-center gap-4">

                    {{-- Initial/avatar --}}
                    <div
                        class="
                            flex size-14 shrink-0 items-center justify-center
                            rounded-2xl
                            bg-[var(--color-accent)]/10
                            text-lg font-semibold
                            text-[var(--color-accent-content)]
                            ring-1 ring-[var(--color-accent)]/15
                            sm:size-16
                            sm:text-xl
                        ">
                        {{-- str()->substr(), not substr(): a name beginning
                             with Ñ is two bytes, and the byte function would
                             cut it in half. --}}
                        {{ str($employee->listing_name)->substr(0, 1)->upper() }}
                    </div>

                    <div class="min-w-0">

                        {{-- The employment status is not repeated here. It has
                             its own card below, and in emerald it would read as
                             a verdict: green is approved everywhere else in
                             this app, and a casual hire is not a worse one. --}}
                        <flux:heading size="xl" class="truncate">
                            {{ $employee->listing_name }}
                        </flux:heading>

                        <div
                            class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">

                            <span>
                                {{ $employee->position?->title ?? '—' }}
                            </span>

                            @if ($employee->section)
                                <span class="text-zinc-300 dark:text-zinc-600">•</span>

                                <span>
                                    {{ $employee->section->name }}
                                </span>
                            @endif

                            @if ($employee->section?->division)
                                <span class="text-zinc-300 dark:text-zinc-600">•</span>

                                <span>
                                    {{ $employee->section->division->name }}
                                </span>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap gap-2">

                    @can('createFor', [App\Models\TrainingRecord::class, $employee])
                        <flux:button variant="primary" icon="plus"
                            wire:click="$dispatch('add-training', { employeeId: {{ $employee->id }} })">
                            {{ __('Add training') }}
                        </flux:button>
                    @endcan

                    <flux:button icon="arrow-down-tray" :href="route('employees.pds', $employee)">
                        {{ __('Download PDS') }}
                    </flux:button>

                </div>

            </div>
        </div>
    </flux:card>


    {{-- Employee information --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Plantilla --}}
        <flux:card class="group relative overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Plantilla item') }}
                    </flux:text>

                    <flux:heading size="lg" class="mt-1 tabular-nums">
                        {{ $employee->item_number ?? '—' }}
                    </flux:heading>
                </div>

                <div class="rounded-xl bg-zinc-100 p-2 dark:bg-white/5">
                    <flux:icon name="identification" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
        </flux:card>


        {{-- Division --}}
        <flux:card class="group relative overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Division') }}
                    </flux:text>

                    <flux:heading size="lg" class="mt-1 truncate"
                        title="{{ $employee->section?->division?->name }}">
                        {{ $employee->section?->division?->name ?? '—' }}
                    </flux:heading>
                </div>

                <div class="rounded-xl bg-zinc-100 p-2 dark:bg-white/5">
                    <flux:icon name="building-office-2" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
        </flux:card>


        {{-- Employment --}}
        <flux:card class="group relative overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Employment status') }}
                    </flux:text>

                    <flux:heading size="lg" class="mt-1">
                        {{ $employee->employment_status->label() }}
                    </flux:heading>
                </div>

                <div class="rounded-xl bg-zinc-100 p-2 dark:bg-white/5">
                    <flux:icon name="briefcase" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
        </flux:card>


        {{-- Date hired --}}
        <flux:card class="group relative overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Date hired') }}
                    </flux:text>

                    <flux:heading size="lg" class="mt-1 tabular-nums">
                        {{ $employee->date_hired?->format('d M Y') ?? '—' }}
                    </flux:heading>
                </div>

                <div class="rounded-xl bg-zinc-100 p-2 dark:bg-white/5">
                    <flux:icon name="calendar-days" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
        </flux:card>

    </div>


    {{-- Training section --}}
    <flux:card class="overflow-hidden p-0">

        {{-- Section header --}}
        <div
            class="flex flex-col gap-4 border-b border-zinc-200 p-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-6">

            <div>
                <flux:heading size="lg">
                    {{ __('Training history') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Learning and development records for this employee.') }}
                </flux:text>
            </div>

            <div
                class="
                    inline-flex w-fit items-center rounded-full
                    bg-zinc-100 px-3 py-1.5
                    text-xs font-medium text-zinc-600
                    dark:bg-white/5 dark:text-zinc-300
                ">
                {{ $this->records->count() }}
                {{ $this->records->count() === 1 ? __('record') : __('records') }}
            </div>

        </div>


        {{-- Training table --}}
        <div class="overflow-x-auto">

            <flux:table>

                <flux:table.columns>

                    <flux:table.column>
                        {{ __('Title') }}
                    </flux:table.column>

                    <flux:table.column>
                        {{ __('Inclusive dates') }}
                    </flux:table.column>

                    <flux:table.column align="end">
                        {{ __('Hours') }}
                    </flux:table.column>

                    <flux:table.column>
                        {{ __('Type of LD') }}
                    </flux:table.column>

                    <flux:table.column>
                        {{ __('Conducted by') }}
                    </flux:table.column>

                    <flux:table.column>
                        {{ __('Status') }}
                    </flux:table.column>

                </flux:table.columns>


                <flux:table.rows>

                    @forelse ($this->records as $record)
                        <flux:table.row :key="$record->id"
                            class="
                                group
                                transition-colors
                                hover:bg-zinc-50
                                dark:hover:bg-white/[0.035]
                            ">

                            {{-- Title --}}
                            <flux:table.cell>

                                <div class="flex min-w-0 items-center gap-3">

                                    <div
                                        class="
                                            flex size-9 shrink-0 items-center justify-center
                                            rounded-lg
                                            bg-[var(--color-accent)]/10
                                            text-[var(--color-accent-content)]
                                        ">
                                        <flux:icon name="academic-cap" class="size-4" />
                                    </div>

                                    {{-- A fixed width, not max-w: a table sizes
                                         its columns to their content, and a long
                                         title would push the status off the
                                         right-hand side. --}}
                                    <div class="w-64 min-w-0 2xl:w-96">

                                        {{-- The accent, as every modal trigger
                                             in a table here wears it: it is the
                                             one thing in the row that opens
                                             something. --}}
                                        <button type="button"
                                            class="
                                                block w-full cursor-pointer truncate
                                                text-left text-sm font-medium
                                                text-[var(--color-accent-content)]
                                                transition
                                                hover:opacity-70
                                            "
                                            title="{{ $record->title }}"
                                            wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                                            {{ $record->title }}
                                        </button>

                                    </div>

                                </div>

                            </flux:table.cell>


                            {{-- Dates --}}
                            <flux:table.cell class="whitespace-nowrap">

                                <span class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $record->inclusive_dates }}
                                </span>

                            </flux:table.cell>


                            {{-- Hours --}}
                            <flux:table.cell align="end">

                                <span class="tabular-nums text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                    {{ $record->hours }}
                                </span>

                            </flux:table.cell>


                            {{-- Type --}}
                            <flux:table.cell>

                                {{-- inline-block, not inline-flex: a flex box
                                     sizes itself to its content and ignores the
                                     width that truncate needs. "Other" carries
                                     whatever the person typed. --}}
                                <span
                                    class="
                                        inline-block max-w-32 truncate
                                        rounded-md
                                        bg-zinc-100 px-2 py-1
                                        text-xs font-medium
                                        text-zinc-600
                                        dark:bg-white/5
                                        dark:text-zinc-300
                                    "
                                    title="{{ $record->ld_type_label }}">
                                    {{ $record->ld_type_label }}
                                </span>

                            </flux:table.cell>


                            {{-- Conducted by --}}
                            <flux:table.cell>

                                <div class="w-40 truncate text-sm text-zinc-600 2xl:w-52 dark:text-zinc-300"
                                    title="{{ $record->conducted_by }}">
                                    {{ $record->conducted_by }}
                                </div>

                            </flux:table.cell>


                            {{-- Status --}}
                            <flux:table.cell>

                                <x-training-status :record="$record" />

                            </flux:table.cell>

                        </flux:table.row>

                    @empty

                        <flux:table.row>

                            <flux:table.cell colspan="6">

                                <div class="flex flex-col items-center justify-center px-6 py-14 text-center">

                                    <div
                                        class="
                                            flex size-12 items-center justify-center
                                            rounded-2xl
                                            bg-zinc-100
                                            dark:bg-white/5
                                        ">
                                        <flux:icon name="academic-cap" class="size-6 text-zinc-400" />
                                    </div>

                                    <flux:heading size="sm" class="mt-4">
                                        {{ __('No training records') }}
                                    </flux:heading>

                                    <flux:text class="mt-1 max-w-sm">
                                        {{ __('No training has been recorded for this employee yet.') }}
                                    </flux:text>

                                    @can('createFor', [App\Models\TrainingRecord::class, $employee])
                                        <flux:button class="mt-4" size="sm" variant="primary" icon="plus"
                                            wire:click="$dispatch('add-training', { employeeId: {{ $employee->id }} })">
                                            {{ __('Add training') }}
                                        </flux:button>
                                    @endcan

                                </div>

                            </flux:table.cell>

                        </flux:table.row>
                    @endforelse

                </flux:table.rows>

            </flux:table>

        </div>

    </flux:card>


    {{-- Modals --}}
    <livewire:pages::trainings.form-modal />

    <livewire:pages::trainings.detail-modal />

</div>
