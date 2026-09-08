<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Enums\LdType;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Record a training')] class extends Component {
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

    public function mount(): void
    {
        $this->employeeId = auth()->user()->employee?->getKey();
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

        $this->authorize('createFor', [TrainingRecord::class, $employee]);

        $record = app(SubmitTrainingRecord::class)->handle(
            $employee,
            [
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
            ],
            auth()->user(),
        );

        Flux::toast(variant: 'success', text: __('Training submitted for approval.'));

        $this->redirectRoute('trainings.show', $record, navigate: true);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Record a training') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            @if ($this->canChooseEmployee)
                <flux:select wire:model="employeeId" :label="__('Employee')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->employees as $employee)
                        <flux:select.option :value="$employee->id">
                            {{ $employee->full_name }} — {{ $employee->employee_number }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="title" :label="__('Title of learning and development intervention')" required />

            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="date_start" :label="__('From')" type="date" required />
                <flux:input wire:model="date_end" :label="__('To')" type="date" required />
                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="ld_type" :label="__('Type of LD')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\LdType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($ld_type === App\Enums\LdType::Other->value)
                    <flux:input wire:model="ld_type_other" :label="__('Specify the type')"
                        :placeholder="__('Soft Skill, Workshop, Convention')" required />
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="conducted_by" :label="__('Conducted or sponsored by')" required />
                <flux:input wire:model="location" :label="__('Location')" />
            </div>

            <flux:separator :text="__('Costs')" />

            <div class="grid gap-4 md:grid-cols-4">
                <flux:input wire:model="registration_fee" :label="__('Registration fee')" type="number" step="0.01" min="0" />
                <flux:input wire:model="tev" :label="__('Travel expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="expenses" :label="__('Other expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="cpd_units" :label="__('CPD units')" type="number" step="0.1" min="0" />
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
                <flux:button :href="route('trainings.mine')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
