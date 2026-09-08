<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Actions\Training\UpdateTrainingRecord;
use App\Enums\LdType;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My trainings')] class extends Component {
    use WithPagination;

    public ?int $editingId = null;

    public ?int $employeeId = null;

    public string $title = '';

    public string $date_start = '';

    public string $date_end = '';

    public ?int $hours = null;

    public string $ld_type = '';

    public string $ld_type_other = '';

    public string $conducted_by = '';

    public string $location = '';

    public ?float $expenses = null;

    public ?float $registration_fee = null;

    public ?float $tev = null;

    public ?float $cpd_units = null;

    /**
     * Approvals are eager loaded because the policy asks whether the
     * record has been acted on for every row.
     *
     * @return LengthAwarePaginator<int, TrainingRecord>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return TrainingRecord::query()
            ->where('employee_id', auth()->user()->employee?->getKey())
            ->with('approvals')
            ->orderByDesc('date_end')
            ->paginate(20);
    }

    /**
     * Only HR and admin choose somebody else; everyone else records their own.
     */
    #[Computed]
    public function canChooseEmployee(): bool
    {
        return auth()->user()->isAdminOrHr();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->resetForm();

        Flux::modal('training-form')->show();
    }

    public function edit(int $recordId): void
    {
        $record = TrainingRecord::findOrFail($recordId);

        $this->authorize('update', $record);

        $this->resetValidation();

        $this->editingId = $record->getKey();
        $this->employeeId = $record->employee_id;
        $this->title = $record->title;
        $this->date_start = $record->date_start->toDateString();
        $this->date_end = $record->date_end->toDateString();
        $this->hours = $record->hours;
        $this->ld_type = $record->ld_type->value;
        $this->ld_type_other = (string) $record->ld_type_other;
        $this->conducted_by = $record->conducted_by;
        $this->location = (string) $record->location;
        $this->expenses = $record->expenses === null ? null : (float) $record->expenses;
        $this->registration_fee = $record->registration_fee === null ? null : (float) $record->registration_fee;
        $this->tev = $record->tev === null ? null : (float) $record->tev;
        $this->cpd_units = $record->cpd_units;

        Flux::modal('training-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'hours' => ['required', 'integer', 'min:1', 'max:9999'],
            'ld_type' => ['required', Rule::enum(LdType::class)],
            'ld_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->ld_type === LdType::Other->value)],
            'conducted_by' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'expenses' => ['nullable', 'numeric', 'min:0'],
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'tev' => ['nullable', 'numeric', 'min:0'],
            'cpd_units' => ['nullable', 'numeric', 'min:0'],
        ]);

        $employee = Employee::findOrFail($validated['employeeId']);

        $attributes = [
            'title' => $validated['title'],
            'date_start' => $validated['date_start'],
            'date_end' => $validated['date_end'],
            'hours' => $validated['hours'],
            'ld_type' => $validated['ld_type'],
            'ld_type_other' => $this->ld_type === LdType::Other->value ? $validated['ld_type_other'] : null,
            'conducted_by' => $validated['conducted_by'],
            'location' => $validated['location'] ?: null,
            'expenses' => $validated['expenses'],
            'registration_fee' => $validated['registration_fee'],
            'tev' => $validated['tev'],
            'cpd_units' => $validated['cpd_units'],
        ];

        if ($this->editingId !== null) {
            $record = TrainingRecord::findOrFail($this->editingId);

            $this->authorize('update', $record);

            app(UpdateTrainingRecord::class)->handle($record, $employee, $attributes);

            $message = __('Training updated.');
        } else {
            $this->authorize('createFor', [TrainingRecord::class, $employee]);

            app(SubmitTrainingRecord::class)->handle($employee, $attributes, auth()->user());

            $message = __('Training submitted for approval.');
        }

        $this->resetForm();

        unset($this->records);

        Flux::modal('training-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'title',
            'date_start',
            'date_end',
            'hours',
            'ld_type',
            'ld_type_other',
            'conducted_by',
            'location',
            'expenses',
            'registration_fee',
            'tev',
            'cpd_units',
        ]);

        $this->employeeId = auth()->user()->employee?->getKey();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('My trainings') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">
            {{ __('Record a training') }}
        </flux:button>
    </div>

    @if (auth()->user()->employee === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Your account is not linked to an employee record yet. Ask HR to link it before recording a training.') }}
        </flux:callout>
    @endif

    <flux:table :paginate="$this->records">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $record->ld_type_label }}</flux:table.cell>
                    <flux:table.cell>
                        <x-training-status :record="$record" />
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $record)
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $record->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No trainings recorded yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="training-form" class="md:w-4xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Record a training') : __('Edit training') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                @if ($this->canChooseEmployee)
                    <flux:select class="md:col-span-2" wire:model="employeeId" :label="__('Employee')" required>
                        <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                        @foreach ($this->employees as $employee)
                            <flux:select.option :value="$employee->id">
                                {{ $employee->full_name }} — {{ $employee->employee_number }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:input class="md:col-span-2" wire:model="title"
                    :label="__('Title of learning and development intervention')" required />

                <flux:input wire:model="date_start" :label="__('From')" type="date" required />
                <flux:input wire:model="date_end" :label="__('To')" type="date" required />

                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />

                <flux:select wire:model.live="ld_type" :label="__('Type of LD')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\LdType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($ld_type === App\Enums\LdType::Other->value)
                    <flux:input class="md:col-span-2" wire:model="ld_type_other" :label="__('Specify the type')"
                        :placeholder="__('Soft Skill, Workshop, Convention')" required />
                @endif

                <flux:input wire:model="conducted_by" :label="__('Conducted or sponsored by')" required />
                <flux:input wire:model="location" :label="__('Location')" />

                <div class="md:col-span-2">
                    <flux:separator :text="__('Costs')" />
                </div>

                <flux:input wire:model="registration_fee" :label="__('Registration fee')" type="number" step="0.01" min="0" />
                <flux:input wire:model="tev" :label="__('Travel expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="expenses" :label="__('Other expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="cpd_units" :label="__('CPD units')" type="number" step="0.1" min="0" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? __('Submit for approval') : __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
