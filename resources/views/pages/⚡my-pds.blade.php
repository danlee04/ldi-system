<?php

use App\Enums\EducationLevel;
use App\Enums\OtherInformationType;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\EmployeeChild;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEligibility;
use App\Models\EmployeeOtherInformation;
use App\Models\EmployeeVoluntaryWork;
use App\Models\EmployeeWorkExperience;
use App\Models\PersonalDataSheet;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The employee's own copy of CS Form No. 212. They fill it in themselves —
 * nobody else knows their blood type or their PhilSys number.
 *
 * Section VI is missing from this page on purpose: their learning and
 * development lines come from the training records this system already
 * approves, so filling them in twice would be asking for two answers.
 */
new #[Title('My PDS')] class extends Component {
    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, array<string, mixed>> */
    public array $education = [];

    /** @var list<array<string, mixed>> */
    public array $eligibilities = [];

    /** @var list<array<string, mixed>> */
    public array $work = [];

    /** @var list<array<string, mixed>> */
    public array $children = [];

    /** @var list<array<string, mixed>> */
    public array $voluntary = [];

    /** @var array<string, list<string>> */
    public array $other = [];

    /**
     * Every repeating section: how many lines the form prints, and the
     * blank line a new one starts from. The cap is the form's, not ours —
     * a line past the last printed row would never reach CSC.
     *
     * @var array<string, array{relation: string, max: int, blank: array<string, mixed>}>
     */
    private const REPEATERS = [
        'eligibilities' => [
            'relation' => 'eligibilities',
            'max' => 7,
            'blank' => [
                'id' => null,
                'eligibility_id' => '',
                'detail' => '',
                'rating' => '',
                'date_of_examination' => '',
                'place_of_examination' => '',
                'license_number' => '',
                'date_of_validity' => '',
            ],
        ],
        'work' => [
            'relation' => 'workExperiences',
            'max' => 24,
            'blank' => [
                'id' => null,
                'from_date' => '',
                'to_date' => '',
                'position_title' => '',
                'agency_name' => '',
                'monthly_salary' => '',
                'salary_grade' => '',
                'appointment_status' => '',
                'is_government' => false,
            ],
        ],
        'children' => [
            'relation' => 'children',
            'max' => 13,
            'blank' => [
                'id' => null,
                'full_name' => '',
                'date_of_birth' => '',
            ],
        ],
        'voluntary' => [
            'relation' => 'voluntaryWorks',
            'max' => 9,
            'blank' => [
                'id' => null,
                'organization' => '',
                'from_date' => '',
                'to_date' => '',
                'hours' => '',
                'position' => '',
            ],
        ],
    ];

    /**
     * Section VIII prints seven lines in each of its three columns.
     */
    public const OTHER_LINES = 7;

    public function mount(): void
    {
        abort_if($this->employee === null, 403, __('Your account is not linked to an employee record.'));

        $sheet = $this->employee->personalDataSheet;

        foreach ($this->fields() as $field) {
            $this->form[$field] = match ($field) {
                'date_of_birth' => $sheet?->date_of_birth?->toDateString() ?? '',
                default => (string) ($sheet?->{$field} ?? ''),
            };
        }

        $rows = $this->employee->educations->keyBy(fn (EmployeeEducation $row): string => $row->level->value);

        foreach (EducationLevel::cases() as $level) {
            $row = $rows->get($level->value);

            $this->education[$level->value] = [
                'school_name' => (string) ($row?->school_name ?? ''),
                'degree_course' => (string) ($row?->degree_course ?? ''),
                'period_from' => (string) ($row?->period_from ?? ''),
                'period_to' => (string) ($row?->period_to ?? ''),
                'highest_level_units' => (string) ($row?->highest_level_units ?? ''),
                'year_graduated' => (string) ($row?->year_graduated ?? ''),
                'honors' => (string) ($row?->honors ?? ''),
            ];
        }

        $this->eligibilities = $this->employee->eligibilities
            ->map(fn (EmployeeEligibility $row): array => [
                'id' => $row->getKey(),
                'eligibility_id' => (string) ($row->eligibility_id ?? ''),
                'detail' => (string) ($row->detail ?? ''),
                'rating' => (string) ($row->rating ?? ''),
                'date_of_examination' => $row->date_of_examination?->toDateString() ?? '',
                'place_of_examination' => (string) ($row->place_of_examination ?? ''),
                'license_number' => (string) ($row->license_number ?? ''),
                'date_of_validity' => $row->date_of_validity?->toDateString() ?? '',
            ])
            ->all();

        $this->work = $this->employee->workExperiences
            ->map(fn (EmployeeWorkExperience $row): array => [
                'id' => $row->getKey(),
                'from_date' => $row->from_date->toDateString(),
                'to_date' => $row->to_date?->toDateString() ?? '',
                'position_title' => $row->position_title,
                'agency_name' => $row->agency_name,
                'monthly_salary' => (string) ($row->monthly_salary ?? ''),
                'salary_grade' => (string) ($row->salary_grade ?? ''),
                'appointment_status' => (string) ($row->appointment_status ?? ''),
                'is_government' => $row->is_government,
            ])
            ->all();

        $this->children = $this->employee->children
            ->map(fn (EmployeeChild $row): array => [
                'id' => $row->getKey(),
                'full_name' => $row->full_name,
                'date_of_birth' => $row->date_of_birth?->toDateString() ?? '',
            ])
            ->all();

        $this->voluntary = $this->employee->voluntaryWorks
            ->map(fn (EmployeeVoluntaryWork $row): array => [
                'id' => $row->getKey(),
                'organization' => $row->organization,
                'from_date' => $row->from_date->toDateString(),
                'to_date' => $row->to_date?->toDateString() ?? '',
                'hours' => (string) ($row->hours ?? ''),
                'position' => (string) ($row->position ?? ''),
            ])
            ->all();

        foreach (array_keys(self::REPEATERS) as $list) {
            if ($this->{$list} === []) {
                $this->addRow($list);
            }
        }

        $lines = $this->employee->otherInformation->groupBy(
            fn (EmployeeOtherInformation $row): string => $row->type->value,
        );

        foreach (OtherInformationType::cases() as $type) {
            $filled = $lines->get($type->value, collect())
                ->pluck('description')
                ->take(self::OTHER_LINES)
                ->values()
                ->all();

            // Always the seven printed lines, so the page looks like the form.
            $this->other[$type->value] = array_pad($filled, self::OTHER_LINES, '');
        }
    }

    /**
     * Adds a blank line to one of the repeating sections.
     *
     * The list name arrives from the browser, so it is checked against the
     * map rather than trusted — otherwise this would write to any public
     * property the component has.
     */
    public function addRow(string $list): void
    {
        $repeater = $this->repeater($list);

        if (count($this->{$list}) >= $repeater['max']) {
            return;
        }

        $this->{$list}[] = $repeater['blank'];
    }

    /**
     * Takes a line off the form. Nothing leaves the database until they
     * save, so a mis-click costs them a reload and no more.
     */
    public function removeRow(string $list, int $index): void
    {
        $this->repeater($list);

        unset($this->{$list}[$index]);

        $this->{$list} = array_values($this->{$list});

        if ($this->{$list} === []) {
            $this->addRow($list);
        }
    }

    /**
     * Whether this section still has room for another line.
     */
    public function roomIn(string $list): bool
    {
        return count($this->{$list}) < $this->repeater($list)['max'];
    }

    /**
     * @return array{relation: string, max: int, blank: array<string, mixed>}
     */
    private function repeater(string $list): array
    {
        abort_unless(array_key_exists($list, self::REPEATERS), 404);

        return self::REPEATERS[$list];
    }

    /**
     * Writes the given lines into their relation and drops the ones that
     * are no longer on the form.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  keyed by their index in $list
     */
    private function syncRows(string $list, Collection $rows): void
    {
        $relation = $this->repeater($list)['relation'];
        $kept = [];

        foreach ($rows as $index => $row) {
            $values = collect($row)
                ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                ->except('id')
                ->all();

            // Scoped to their own lines, so a tampered id finds nothing and
            // starts a new one instead of editing somebody else's.
            $record = $this->employee->{$relation}()->findOrNew($this->{$list}[$index]['id'] ?? 0);

            $record->fill($values)->save();

            $kept[] = $record->getKey();
            $this->{$list}[$index]['id'] = $record->getKey();
        }

        $this->employee->{$relation}()->reorder()->whereNotIn('id', $kept)->delete();
        $this->employee->unsetRelation($relation);
    }

    public function saveEligibilities(): void
    {
        $validated = $this->validate([
            'eligibilities' => ['array', 'max:'.self::REPEATERS['eligibilities']['max']],
            'eligibilities.*.eligibility_id' => ['nullable', 'integer', 'exists:eligibilities,id'],
            'eligibilities.*.detail' => ['nullable', 'string', 'max:255'],
            'eligibilities.*.rating' => ['nullable', 'string', 'max:40'],
            'eligibilities.*.date_of_examination' => ['nullable', 'date'],
            'eligibilities.*.place_of_examination' => ['nullable', 'string', 'max:255'],
            'eligibilities.*.license_number' => ['nullable', 'string', 'max:60'],
            'eligibilities.*.date_of_validity' => ['nullable', 'date', 'after:eligibilities.*.date_of_examination'],
        ], attributes: [
            'eligibilities.*.eligibility_id' => __('eligibility'),
            'eligibilities.*.date_of_validity' => __('date of validity'),
            'eligibilities.*.date_of_examination' => __('date of examination'),
        ]);

        // A line with nothing on it is not an eligibility.
        $this->syncRows('eligibilities', collect($validated['eligibilities'])
            ->filter(fn (array $row): bool => filled($row['eligibility_id']) || filled($row['detail'])));

        Flux::toast(variant: 'success', text: __('Eligibility saved.'));
    }

    /**
     * @return Collection<int, Eligibility>
     */
    #[Computed(persist: true)]
    public function eligibilityList(): Collection
    {
        return Eligibility::query()->orderBy('name')->get();
    }

    public function saveWork(): void
    {
        $validated = $this->validate([
            'work' => ['array', 'max:'.self::REPEATERS['work']['max']],
            // A posting is only printable with all three. Naming the other
            // two makes any one of them compulsory, so a half-typed line
            // is caught here instead of vanishing on save.
            'work.*.from_date' => ['nullable', 'date', 'before_or_equal:today', 'required_with:work.*.position_title,work.*.agency_name'],
            'work.*.to_date' => ['nullable', 'date', 'after_or_equal:work.*.from_date'],
            'work.*.position_title' => ['nullable', 'string', 'max:255', 'required_with:work.*.from_date,work.*.agency_name'],
            'work.*.agency_name' => ['nullable', 'string', 'max:255', 'required_with:work.*.from_date,work.*.position_title'],
            'work.*.monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'work.*.salary_grade' => ['nullable', 'string', 'max:20'],
            'work.*.appointment_status' => ['nullable', 'string', 'max:60'],
            'work.*.is_government' => ['boolean'],
        ], attributes: [
            'work.*.from_date' => __('date from'),
            'work.*.to_date' => __('date to'),
            'work.*.position_title' => __('position title'),
            'work.*.agency_name' => __('department or company'),
            'work.*.monthly_salary' => __('monthly salary'),
        ]);

        $this->syncRows('work', collect($validated['work'])
            ->filter(fn (array $row): bool => filled($row['from_date'])));

        Flux::toast(variant: 'success', text: __('Work experience saved.'));
    }

    public function saveFamily(): void
    {
        $validated = $this->validate([
            'form.spouse_last_name' => ['nullable', 'string', 'max:255'],
            'form.spouse_first_name' => ['nullable', 'string', 'max:255'],
            'form.spouse_middle_name' => ['nullable', 'string', 'max:255'],
            'form.spouse_suffix' => ['nullable', 'string', 'max:20'],
            'form.spouse_occupation' => ['nullable', 'string', 'max:255'],
            'form.spouse_employer' => ['nullable', 'string', 'max:255'],
            'form.spouse_business_address' => ['nullable', 'string', 'max:255'],
            'form.spouse_telephone_no' => ['nullable', 'string', 'max:40'],
            'form.father_last_name' => ['nullable', 'string', 'max:255'],
            'form.father_first_name' => ['nullable', 'string', 'max:255'],
            'form.father_middle_name' => ['nullable', 'string', 'max:255'],
            'form.father_suffix' => ['nullable', 'string', 'max:20'],
            'form.mother_last_name' => ['nullable', 'string', 'max:255'],
            'form.mother_first_name' => ['nullable', 'string', 'max:255'],
            'form.mother_middle_name' => ['nullable', 'string', 'max:255'],
            'children' => ['array', 'max:'.self::REPEATERS['children']['max']],
            'children.*.full_name' => ['nullable', 'string', 'max:255', 'required_with:children.*.date_of_birth'],
            'children.*.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
        ], attributes: [
            'children.*.full_name' => __('name of child'),
            'children.*.date_of_birth' => __('date of birth'),
        ]);

        $this->writeSheet($validated['form']);

        $this->syncRows('children', collect($validated['children'])
            ->filter(fn (array $row): bool => filled($row['full_name'])));

        Flux::toast(variant: 'success', text: __('Family background saved.'));
    }

    public function saveVoluntary(): void
    {
        $validated = $this->validate([
            'voluntary' => ['array', 'max:'.self::REPEATERS['voluntary']['max']],
            'voluntary.*.organization' => ['nullable', 'string', 'max:255', 'required_with:voluntary.*.from_date'],
            'voluntary.*.from_date' => ['nullable', 'date', 'before_or_equal:today', 'required_with:voluntary.*.organization'],
            'voluntary.*.to_date' => ['nullable', 'date', 'after_or_equal:voluntary.*.from_date'],
            'voluntary.*.hours' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'voluntary.*.position' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'voluntary.*.organization' => __('organisation'),
            'voluntary.*.from_date' => __('date from'),
            'voluntary.*.to_date' => __('date to'),
        ]);

        $this->syncRows('voluntary', collect($validated['voluntary'])
            ->filter(fn (array $row): bool => filled($row['organization'])));

        Flux::toast(variant: 'success', text: __('Voluntary work saved.'));
    }

    public function saveOther(): void
    {
        $validated = $this->validate([
            'other' => ['array'],
            'other.*' => ['array', 'max:'.self::OTHER_LINES],
            'other.*.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Three plain lists with nothing to key them by, so they are
        // rewritten whole rather than matched line by line.
        $this->employee->otherInformation()->delete();

        foreach (OtherInformationType::cases() as $type) {
            foreach ($validated['other'][$type->value] ?? [] as $description) {
                if (blank($description)) {
                    continue;
                }

                $this->employee->otherInformation()->create([
                    'type' => $type,
                    'description' => $description,
                ]);
            }
        }

        $this->employee->unsetRelation('otherInformation');

        Flux::toast(variant: 'success', text: __('Other information saved.'));
    }

    public function saveEducation(): void
    {
        $validated = $this->validate([
            'education.*.school_name' => ['nullable', 'string', 'max:255'],
            'education.*.degree_course' => ['nullable', 'string', 'max:255'],
            'education.*.period_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.period_to' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:education.*.period_from'],
            'education.*.highest_level_units' => ['nullable', 'string', 'max:255'],
            'education.*.year_graduated' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.honors' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['education'] as $level => $row) {
            $values = collect($row)->map(fn (mixed $v): mixed => $v === '' ? null : $v)->all();

            // A level nobody attended leaves no line on the form.
            if (collect($values)->filter()->isEmpty()) {
                EmployeeEducation::query()
                    ->where('employee_id', $this->employee->getKey())
                    ->where('level', $level)
                    ->delete();

                continue;
            }

            EmployeeEducation::updateOrCreate(
                ['employee_id' => $this->employee->getKey(), 'level' => $level],
                $values,
            );
        }

        $this->employee->unsetRelation('educations');

        Flux::toast(variant: 'success', text: __('Education saved.'));
    }

    #[Computed(persist: true)]
    public function employee(): ?Employee
    {
        return auth()->user()->employee;
    }

    #[Computed]
    public function completeness(): int
    {
        return $this->employee->personalDataSheet?->completeness() ?? 0;
    }

    /**
     * @return list<string>
     */
    private function fields(): array
    {
        return (new PersonalDataSheet)->getFillable();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.date_of_birth' => ['nullable', 'date', 'before:today'],
            'form.place_of_birth' => ['nullable', 'string', 'max:255'],
            'form.civil_status' => ['nullable', 'string', 'max:30'],
            'form.civil_status_other' => ['nullable', 'string', 'max:255', 'required_if:form.civil_status,Others'],
            'form.citizenship' => ['nullable', 'string', 'max:60'],
            'form.dual_citizenship_country' => ['nullable', 'string', 'max:255'],
            'form.height_m' => ['nullable', 'numeric', 'between:0.5,2.5'],
            'form.weight_kg' => ['nullable', 'numeric', 'between:20,300'],
            'form.blood_type' => ['nullable', 'string', 'max:10'],
            'form.umid_id_no' => ['nullable', 'string', 'max:40'],
            'form.pagibig_id_no' => ['nullable', 'string', 'max:40'],
            'form.philhealth_no' => ['nullable', 'string', 'max:40'],
            'form.philsys_card_number' => ['nullable', 'string', 'max:40'],
            'form.tin_no' => ['nullable', 'string', 'max:40'],
            'form.agency_employee_no' => ['nullable', 'string', 'max:40'],
            'form.residential_house_block_lot' => ['nullable', 'string', 'max:255'],
            'form.residential_street' => ['nullable', 'string', 'max:255'],
            'form.residential_subdivision' => ['nullable', 'string', 'max:255'],
            'form.residential_barangay' => ['nullable', 'string', 'max:255'],
            'form.residential_city' => ['nullable', 'string', 'max:255'],
            'form.residential_province' => ['nullable', 'string', 'max:255'],
            'form.residential_zip' => ['nullable', 'string', 'max:10'],
            'form.permanent_house_block_lot' => ['nullable', 'string', 'max:255'],
            'form.permanent_street' => ['nullable', 'string', 'max:255'],
            'form.permanent_subdivision' => ['nullable', 'string', 'max:255'],
            'form.permanent_barangay' => ['nullable', 'string', 'max:255'],
            'form.permanent_city' => ['nullable', 'string', 'max:255'],
            'form.permanent_province' => ['nullable', 'string', 'max:255'],
            'form.permanent_zip' => ['nullable', 'string', 'max:10'],
            'form.telephone_no' => ['nullable', 'string', 'max:40'],
            'form.mobile_no' => ['nullable', 'string', 'max:40'],
            'form.email_address' => ['nullable', 'email', 'max:255'],
        ]);

        $this->writeSheet($validated['form']);

        unset($this->completeness);

        Flux::toast(variant: 'success', text: __('Saved. Nobody else can edit this but you.'));
    }

    /**
     * Writes part of Section I or II, leaving the rest of the row alone —
     * each form on this page saves only the fields it shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function writeSheet(array $attributes): void
    {
        PersonalDataSheet::updateOrCreate(
            ['employee_id' => $this->employee->getKey()],
            collect($attributes)
                ->except('employee_id')
                ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                ->all(),
        );

        $this->employee->unsetRelation('personalDataSheet');
    }

    /**
     * Copying the residential address is the commonest thing on this page
     * and the most tedious to retype.
     */
    public function copyResidentialToPermanent(): void
    {
        foreach (['house_block_lot', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip'] as $part) {
            $this->form['permanent_'.$part] = $this->form['residential_'.$part];
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My personal data sheet') }}</flux:heading>
            <flux:text>{{ __('CS Form No. 212 (Revised 2026)') }}</flux:text>
        </div>

        <flux:button icon="arrow-down-tray" :href="route('my-pds.download')">
            {{ __('Download PDS') }}
        </flux:button>
    </div>

    <flux:callout icon="lock-closed">
        {{ __('Only you can edit this. HR can print it for your 201 file but cannot fill it in for you.') }}
    </flux:callout>

    <div>
        <div class="mb-1 flex items-center justify-between text-sm">
            <span>{{ __('Section I completeness') }}</span>
            <span class="tabular-nums">{{ $this->completeness }}%</span>
        </div>
        <flux:progress :value="$this->completeness" />
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:separator :text="__('I. Personal information')" />

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="form.date_of_birth" :label="__('Date of birth')" type="date" />
            <flux:input class="md:col-span-2" wire:model="form.place_of_birth" :label="__('Place of birth')" />

            <flux:select wire:model.live="form.civil_status" :label="__('Civil status')">
                <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                @foreach (App\Models\PersonalDataSheet::civilStatuses() as $status)
                    <flux:select.option :value="$status">{{ $status }}</flux:select.option>
                @endforeach
            </flux:select>

            @if (($form['civil_status'] ?? '') === 'Others')
                <flux:input wire:model="form.civil_status_other" :label="__('Specify')" required />
            @endif

            <flux:input wire:model="form.citizenship" :label="__('Citizenship')" placeholder="Filipino" />
            <flux:input wire:model="form.dual_citizenship_country" :label="__('If dual, which country')" />

            <flux:input wire:model="form.height_m" :label="__('Height (m)')" type="number" step="0.01" />
            <flux:input wire:model="form.weight_kg" :label="__('Weight (kg)')" type="number" step="0.01" />
            <flux:input wire:model="form.blood_type" :label="__('Blood type')" placeholder="O+" />
        </div>

        <flux:separator :text="__('Government numbers')" />

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="form.umid_id_no" :label="__('UMID ID no.')" />
            <flux:input wire:model="form.pagibig_id_no" :label="__('Pag-IBIG ID no.')" />
            <flux:input wire:model="form.philhealth_no" :label="__('PhilHealth no.')" />
            <flux:input wire:model="form.philsys_card_number" :label="__('PhilSys card number')" />
            <flux:input wire:model="form.tin_no" :label="__('TIN no.')" />
            <flux:input wire:model="form.agency_employee_no" :label="__('Agency employee no.')" />
        </div>

        <flux:separator :text="__('Residential address')" />

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="form.residential_house_block_lot" :label="__('House/Block/Lot no.')" />
            <flux:input wire:model="form.residential_street" :label="__('Street')" />
            <flux:input wire:model="form.residential_subdivision" :label="__('Subdivision/Village')" />
            <flux:input wire:model="form.residential_barangay" :label="__('Barangay')" />
            <flux:input wire:model="form.residential_city" :label="__('City/Municipality')" />
            <flux:input wire:model="form.residential_province" :label="__('Province')" />
            <flux:input wire:model="form.residential_zip" :label="__('ZIP code')" />
        </div>

        <div class="flex items-center gap-3">
            <flux:separator class="flex-1" />
            <flux:button size="sm" variant="ghost" type="button" wire:click="copyResidentialToPermanent">
                {{ __('Same as residential') }}
            </flux:button>
        </div>

        <flux:separator :text="__('Permanent address')" />

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="form.permanent_house_block_lot" :label="__('House/Block/Lot no.')" />
            <flux:input wire:model="form.permanent_street" :label="__('Street')" />
            <flux:input wire:model="form.permanent_subdivision" :label="__('Subdivision/Village')" />
            <flux:input wire:model="form.permanent_barangay" :label="__('Barangay')" />
            <flux:input wire:model="form.permanent_city" :label="__('City/Municipality')" />
            <flux:input wire:model="form.permanent_province" :label="__('Province')" />
            <flux:input wire:model="form.permanent_zip" :label="__('ZIP code')" />
        </div>

        <flux:separator :text="__('Contact')" />

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="form.telephone_no" :label="__('Telephone no.')" />
            <flux:input wire:model="form.mobile_no" :label="__('Mobile no.')" />
            <flux:input wire:model="form.email_address" :label="__('E-mail address')" type="email" />
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Save personal information') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveFamily" class="space-y-4">
        <flux:separator :text="__('II. Family background')" />

        <flux:text size="sm">{{ __('Spouse') }}</flux:text>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="form.spouse_last_name" :label="__('Surname')" />
            <flux:input wire:model="form.spouse_first_name" :label="__('First name')" />
            <flux:input wire:model="form.spouse_middle_name" :label="__('Middle name')" />
            <flux:input wire:model="form.spouse_suffix" :label="__('Name extension')" :placeholder="__('Jr., Sr.')" />

            <flux:input wire:model="form.spouse_occupation" :label="__('Occupation')" />
            <flux:input wire:model="form.spouse_employer" :label="__('Employer or business name')" />
            <flux:input wire:model="form.spouse_business_address" :label="__('Business address')" />
            <flux:input wire:model="form.spouse_telephone_no" :label="__('Telephone no.')" />
        </div>

        <flux:text size="sm">{{ __('Father') }}</flux:text>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="form.father_last_name" :label="__('Surname')" />
            <flux:input wire:model="form.father_first_name" :label="__('First name')" />
            <flux:input wire:model="form.father_middle_name" :label="__('Middle name')" />
            <flux:input wire:model="form.father_suffix" :label="__('Name extension')" :placeholder="__('Jr., Sr.')" />
        </div>

        <flux:text size="sm">{{ __("Mother's maiden name") }}</flux:text>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="form.mother_last_name" :label="__('Surname')" />
            <flux:input wire:model="form.mother_first_name" :label="__('First name')" />
            <flux:input wire:model="form.mother_middle_name" :label="__('Middle name')" />
        </div>

        <flux:text size="sm">{{ __('Children — list all of them, oldest first.') }}</flux:text>

        <div class="space-y-3">
            @foreach ($children as $index => $row)
                <div wire:key="child-{{ $index }}" class="grid items-end gap-4 md:grid-cols-4">
                    <flux:input class="md:col-span-2" wire:model="children.{{ $index }}.full_name"
                        :label="$index === 0 ? __('Full name') : null" />

                    <flux:input wire:model="children.{{ $index }}.date_of_birth" type="date"
                        :label="$index === 0 ? __('Date of birth') : null" />

                    <div class="flex justify-start">
                        <flux:button size="sm" variant="subtle" icon="trash" type="button"
                            wire:click="removeRow('children', {{ $index }})">
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if ($this->roomIn('children'))
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addRow('children')">
                    {{ __('Add child') }}
                </flux:button>
            @else
                <flux:text size="sm">{{ __('The form has room for thirteen.') }}</flux:text>
            @endif

            <flux:spacer />

            <flux:button type="submit" variant="primary">{{ __('Save family background') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveEducation" class="space-y-4">
        <flux:separator :text="__('III. Educational background')" />

        <flux:text size="sm">
            {{ __('Leave a level blank if you did not attend it — it will print as an empty line.') }}
        </flux:text>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] border-separate border-spacing-y-3">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 dark:text-zinc-400">
                        <th class="w-32 pe-3 font-normal">{{ __('Level') }}</th>
                        <th class="pe-3 font-normal">{{ __('Name of school') }}</th>
                        <th class="pe-3 font-normal">{{ __('Degree or course') }}</th>
                        <th class="w-20 pe-3 font-normal">{{ __('From') }}</th>
                        <th class="w-20 pe-3 font-normal">{{ __('To') }}</th>
                        <th class="w-32 pe-3 font-normal">{{ __('Units earned') }}</th>
                        <th class="w-24 pe-3 font-normal">{{ __('Graduated') }}</th>
                        <th class="w-40 font-normal">{{ __('Honors') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach (App\Enums\EducationLevel::cases() as $level)
                        <tr wire:key="education-{{ $level->value }}">
                            <td class="pe-3 align-middle text-sm">{{ $level->label() }}</td>
                            <td class="pe-3">
                                <flux:input size="sm" wire:model="education.{{ $level->value }}.school_name" />
                            </td>
                            <td class="pe-3">
                                <flux:input size="sm" wire:model="education.{{ $level->value }}.degree_course" />
                            </td>
                            <td class="pe-3">
                                <flux:input size="sm" type="number" wire:model="education.{{ $level->value }}.period_from" />
                            </td>
                            <td class="pe-3">
                                <flux:input size="sm" type="number" wire:model="education.{{ $level->value }}.period_to" />
                            </td>
                            <td class="pe-3">
                                <flux:input size="sm" wire:model="education.{{ $level->value }}.highest_level_units" />
                            </td>
                            <td class="pe-3">
                                <flux:input size="sm" type="number" wire:model="education.{{ $level->value }}.year_graduated" />
                            </td>
                            <td>
                                <flux:input size="sm" wire:model="education.{{ $level->value }}.honors" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Save education') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveEligibilities" class="space-y-4">
        <flux:separator :text="__('IV. Civil service eligibility')" />

        <flux:text size="sm">
            {{ __('One line per eligibility or licence. Leave the date of validity blank if it never expires.') }}
        </flux:text>

        <div class="space-y-4">
            @foreach ($eligibilities as $index => $row)
                <div wire:key="eligibility-{{ $index }}"
                    class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-4 md:grid-cols-4">
                        <flux:select class="md:col-span-2" wire:model="eligibilities.{{ $index }}.eligibility_id"
                            :label="__('Eligibility')">
                            <flux:select.option value="">{{ __('Select or type below') }}</flux:select.option>
                            @foreach ($this->eligibilityList as $eligibility)
                                <flux:select.option :value="$eligibility->id">{{ $eligibility->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input class="md:col-span-2" wire:model="eligibilities.{{ $index }}.detail"
                            :label="__('Or write it in full')"
                            :placeholder="__('Registered Nurse, PRC')" />

                        <flux:input wire:model="eligibilities.{{ $index }}.rating" :label="__('Rating')" />
                        <flux:input wire:model="eligibilities.{{ $index }}.date_of_examination"
                            :label="__('Date of examination')" type="date" />
                        <flux:input class="md:col-span-2" wire:model="eligibilities.{{ $index }}.place_of_examination"
                            :label="__('Place of examination')" />

                        <flux:input class="md:col-span-2" wire:model="eligibilities.{{ $index }}.license_number"
                            :label="__('Licence number')" />
                        <flux:input wire:model="eligibilities.{{ $index }}.date_of_validity"
                            :label="__('Date of validity')" type="date" />

                        <div class="flex items-end justify-end">
                            <flux:button size="sm" variant="subtle" icon="trash" type="button"
                                wire:click="removeRow('eligibilities', {{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if ($this->roomIn('eligibilities'))
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addRow('eligibilities')">
                    {{ __('Add eligibility') }}
                </flux:button>
            @else
                <flux:text size="sm">{{ __('The form has room for seven.') }}</flux:text>
            @endif

            <flux:spacer />

            <flux:button type="submit" variant="primary">{{ __('Save eligibility') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveWork" class="space-y-4">
        <flux:separator :text="__('V. Work experience')" />

        <flux:text size="sm">
            {{ __('Include private employment. Leave the end date blank for the post you hold now — it prints as "Present".') }}
        </flux:text>

        <div class="space-y-4">
            @foreach ($work as $index => $row)
                <div wire:key="work-{{ $index }}"
                    class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-4 md:grid-cols-4">
                        <flux:input class="md:col-span-2" wire:model="work.{{ $index }}.position_title"
                            :label="__('Position title')"
                            :placeholder="__('Write it in full, do not abbreviate')" />

                        <flux:input class="md:col-span-2" wire:model="work.{{ $index }}.agency_name"
                            :label="__('Department, agency or company')" />

                        <flux:input wire:model="work.{{ $index }}.from_date" :label="__('From')" type="date" />
                        <flux:input wire:model="work.{{ $index }}.to_date" :label="__('To')" type="date"
                            :description="__('Blank if present')" />

                        <flux:input wire:model="work.{{ $index }}.monthly_salary" :label="__('Monthly salary')"
                            type="number" step="0.01" />

                        <flux:input wire:model="work.{{ $index }}.salary_grade" :label="__('Salary grade and step')"
                            placeholder="11-1" />

                        <flux:select class="md:col-span-2" wire:model="work.{{ $index }}.appointment_status"
                            :label="__('Status of appointment')">
                            <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                            @foreach (App\Models\EmployeeWorkExperience::appointmentStatuses() as $status)
                                <flux:select.option :value="$status">{{ $status }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:field variant="inline" class="self-end">
                            <flux:switch wire:model="work.{{ $index }}.is_government" />
                            <flux:label>{{ __('Government service') }}</flux:label>
                        </flux:field>

                        <div class="flex items-end justify-end">
                            <flux:button size="sm" variant="subtle" icon="trash" type="button"
                                wire:click="removeRow('work', {{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if ($this->roomIn('work'))
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addRow('work')">
                    {{ __('Add work experience') }}
                </flux:button>
            @else
                <flux:text size="sm">{{ __('The form has room for twenty-four.') }}</flux:text>
            @endif

            <flux:spacer />

            <flux:button type="submit" variant="primary">{{ __('Save work experience') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveVoluntary" class="space-y-4">
        <flux:separator :text="__('VII. Voluntary work')" />

        <flux:text size="sm">
            {{ __('Voluntary work or involvement in civic or non-government organisations.') }}
        </flux:text>

        <div class="space-y-4">
            @foreach ($voluntary as $index => $row)
                <div wire:key="voluntary-{{ $index }}"
                    class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-4 md:grid-cols-4">
                        <flux:input class="md:col-span-2" wire:model="voluntary.{{ $index }}.organization"
                            :label="__('Name and address of organisation')" />

                        <flux:input class="md:col-span-2" wire:model="voluntary.{{ $index }}.position"
                            :label="__('Position or nature of work')" />

                        <flux:input wire:model="voluntary.{{ $index }}.from_date" :label="__('From')" type="date" />
                        <flux:input wire:model="voluntary.{{ $index }}.to_date" :label="__('To')" type="date"
                            :description="__('Blank if ongoing')" />

                        <flux:input wire:model="voluntary.{{ $index }}.hours" :label="__('Number of hours')"
                            type="number" />

                        <div class="flex items-end justify-end">
                            <flux:button size="sm" variant="subtle" icon="trash" type="button"
                                wire:click="removeRow('voluntary', {{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if ($this->roomIn('voluntary'))
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addRow('voluntary')">
                    {{ __('Add voluntary work') }}
                </flux:button>
            @else
                <flux:text size="sm">{{ __('The form has room for nine.') }}</flux:text>
            @endif

            <flux:spacer />

            <flux:button type="submit" variant="primary">{{ __('Save voluntary work') }}</flux:button>
        </div>
    </form>

    <form wire:submit="saveOther" class="space-y-4">
        <flux:separator :text="__('VIII. Other information')" />

        <flux:text size="sm">{{ __('Seven lines each, the way the form prints them.') }}</flux:text>

        <div class="grid gap-6 md:grid-cols-3">
            @foreach (App\Enums\OtherInformationType::cases() as $type)
                <div class="space-y-2">
                    <flux:text size="sm" class="font-medium">{{ $type->label() }}</flux:text>

                    @foreach ($other[$type->value] as $line => $description)
                        <flux:input size="sm" wire:key="other-{{ $type->value }}-{{ $line }}"
                            wire:model="other.{{ $type->value }}.{{ $line }}" />
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Save other information') }}</flux:button>
        </div>
    </form>
</div>
