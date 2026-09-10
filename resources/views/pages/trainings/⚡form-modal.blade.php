<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Actions\Training\UpdateTrainingRecord;
use App\Enums\LdType;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The form a training record is written on, wherever that happens.
 *
 * Include it once per page and open it with
 * `$dispatch('add-training')`, `$dispatch('add-training', { employeeId: n })`
 * for somebody in particular, or `$dispatch('edit-training', { recordId: n })`.
 * It dispatches `training-saved` afterwards so the list behind it refreshes.
 *
 * It lives here rather than on the pages because the same twelve fields and
 * their validation would otherwise be written out twice and drift apart.
 */
new class extends Component {
    public ?int $editingId = null;

    public ?int $employeeId = null;

    /**
     * Set when the page opened the form for one person — an employee's
     * profile — so the picker is not offered at all.
     */
    public ?int $fixedEmployeeId = null;

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

    #[On('add-training')]
    public function add(?int $employeeId = null): void
    {
        $this->resetValidation();
        $this->resetForm();

        $this->fixedEmployeeId = $employeeId;

        if ($employeeId !== null) {
            $this->employeeId = $employeeId;
        }

        Flux::modal('training-form')->show();
    }

    #[On('edit-training')]
    public function edit(int $recordId): void
    {
        $record = TrainingRecord::findOrFail($recordId);

        $this->authorize('update', $record);

        $this->resetValidation();

        $this->editingId = $record->getKey();
        $this->employeeId = $record->employee_id;
        $this->fixedEmployeeId = null;
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

    /**
     * Only HR and admin choose somebody else, and only when the page has
     * not already said who this is for.
     */
    #[Computed]
    public function canChooseEmployee(): bool
    {
        return $this->fixedEmployeeId === null && auth()->user()->isAdminOrHr();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    /**
     * Whose record this is, when the page fixed it — shown instead of the
     * picker so it is never a mystery which file this lands in.
     */
    #[Computed]
    public function fixedEmployee(): ?Employee
    {
        return $this->fixedEmployeeId === null ? null : Employee::find($this->fixedEmployeeId);
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

        Flux::modal('training-form')->close();

        Flux::toast(variant: 'success', text: $message);

        $this->dispatch('training-saved');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'fixedEmployeeId',
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

<flux:modal name="training-form" class="md:w-7xl">
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
                            {{ $employee->listing_name }} — {{ $employee->employee_number }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($this->fixedEmployee)
                <div class="md:col-span-2">
                    <flux:text size="sm">{{ __('Employee') }}</flux:text>
                    <flux:heading>{{ $this->fixedEmployee->listing_name }}</flux:heading>
                </div>
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
