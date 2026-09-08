<?php

use App\Enums\EducationLevel;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEligibility;
use App\Models\EmployeeWorkExperience;
use App\Models\PersonalDataSheet;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The employee's own copy of CS Form No. 212. They fill it in themselves —
 * nobody else knows their blood type or their PhilSys number.
 */
new #[Title('My PDS')] class extends Component {
    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, array<string, mixed>> */
    public array $education = [];

    /** @var list<array<string, mixed>> */
    public array $eligibilities = [];

    /**
     * The form prints seven lines of Section IV and no more.
     */
    public const MAX_ELIGIBILITIES = 7;

    /** @var list<array<string, mixed>> */
    public array $work = [];

    /**
     * Rows 18 to 41 of the second sheet: twenty-four postings.
     */
    public const MAX_WORK = 24;

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

        if ($this->eligibilities === []) {
            $this->addEligibility();
        }

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

        if ($this->work === []) {
            $this->addWork();
        }
    }

    public function addEligibility(): void
    {
        if (count($this->eligibilities) >= self::MAX_ELIGIBILITIES) {
            return;
        }

        $this->eligibilities[] = [
            'id' => null,
            'eligibility_id' => '',
            'detail' => '',
            'rating' => '',
            'date_of_examination' => '',
            'place_of_examination' => '',
            'license_number' => '',
            'date_of_validity' => '',
        ];
    }

    /**
     * Takes the line off the form. Nothing leaves the database until they
     * save, so a mis-click costs them a reload and no more.
     */
    public function removeEligibility(int $index): void
    {
        unset($this->eligibilities[$index]);

        $this->eligibilities = array_values($this->eligibilities);

        if ($this->eligibilities === []) {
            $this->addEligibility();
        }
    }

    public function saveEligibilities(): void
    {
        $validated = $this->validate([
            'eligibilities' => ['array', 'max:'.self::MAX_ELIGIBILITIES],
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

        $kept = [];

        foreach ($validated['eligibilities'] as $index => $row) {
            $values = collect($row)->map(fn (mixed $v): mixed => $v === '' ? null : $v)->all();

            // A line with nothing on it is not an eligibility.
            if ($values['eligibility_id'] === null && $values['detail'] === null) {
                continue;
            }

            unset($values['id']);

            // Scoped to their own rows, so a tampered id finds nothing and
            // starts a new line instead of editing somebody else's.
            $record = $this->employee->eligibilities()
                ->findOrNew($this->eligibilities[$index]['id'] ?? 0);

            $record->fill($values)->save();

            $kept[] = $record->getKey();
            $this->eligibilities[$index]['id'] = $record->getKey();
        }

        EmployeeEligibility::query()
            ->where('employee_id', $this->employee->getKey())
            ->whereNotIn('id', $kept)
            ->delete();

        $this->employee->unsetRelation('eligibilities');

        Flux::toast(variant: 'success', text: __('Eligibility saved.'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Eligibility>
     */
    #[Computed(persist: true)]
    public function eligibilityList(): \Illuminate\Support\Collection
    {
        return Eligibility::query()->orderBy('name')->get();
    }

    public function addWork(): void
    {
        if (count($this->work) >= self::MAX_WORK) {
            return;
        }

        $this->work[] = [
            'id' => null,
            'from_date' => '',
            'to_date' => '',
            'position_title' => '',
            'agency_name' => '',
            'monthly_salary' => '',
            'salary_grade' => '',
            'appointment_status' => '',
            'is_government' => false,
        ];
    }

    public function removeWork(int $index): void
    {
        unset($this->work[$index]);

        $this->work = array_values($this->work);

        if ($this->work === []) {
            $this->addWork();
        }
    }

    public function saveWork(): void
    {
        $validated = $this->validate([
            'work' => ['array', 'max:'.self::MAX_WORK],
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

        $filled = collect($validated['work'])
            ->filter(fn (array $row): bool => filled($row['from_date']));

        $kept = [];

        foreach ($filled as $index => $row) {
            $values = collect($row)
                ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                ->except('id')
                ->all();

            $record = $this->employee->workExperiences()->findOrNew($this->work[$index]['id'] ?? 0);

            $record->fill($values)->save();

            $kept[] = $record->getKey();
            $this->work[$index]['id'] = $record->getKey();
        }

        EmployeeWorkExperience::query()
            ->where('employee_id', $this->employee->getKey())
            ->whereNotIn('id', $kept)
            ->delete();

        $this->employee->unsetRelation('workExperiences');

        Flux::toast(variant: 'success', text: __('Work experience saved.'));
    }

    #[Computed]
    public function maxWork(): int
    {
        return self::MAX_WORK;
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
    public function maxEligibilities(): int
    {
        return self::MAX_ELIGIBILITIES;
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

        $attributes = collect($validated['form'])
            ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
            ->except('employee_id')
            ->all();

        PersonalDataSheet::updateOrCreate(['employee_id' => $this->employee->getKey()], $attributes);

        unset($this->completeness);

        Flux::toast(variant: 'success', text: __('Saved. Nobody else can edit this but you.'));
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
                                wire:click="removeEligibility({{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if (count($eligibilities) < $this->maxEligibilities)
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addEligibility">
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
                                wire:click="removeWork({{ $index }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if (count($work) < $this->maxWork)
                <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addWork">
                    {{ __('Add work experience') }}
                </flux:button>
            @else
                <flux:text size="sm">{{ __('The form has room for twenty-four.') }}</flux:text>
            @endif

            <flux:spacer />

            <flux:button type="submit" variant="primary">{{ __('Save work experience') }}</flux:button>
        </div>
    </form>
</div>
