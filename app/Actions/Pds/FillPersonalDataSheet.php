<?php

namespace App\Actions\Pds;

use App\Models\Employee;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Fills the agency's own copy of CS Form No. 212 (Revised 2026).
 *
 * The output is the CSC workbook itself, not a lookalike: the template in
 * storage is opened and written into, so every border, checkbox and page
 * break is the commission's own.
 *
 * Cell addresses are declared here, in one map per section. The form puts
 * its caption *below* the box it labels, so the address of an answer is
 * one row above the words describing it — worth knowing before changing
 * any of these.
 */
class FillPersonalDataSheet
{
    /**
     * Section I. Personal information.
     *
     * @var array<string, string>
     */
    private const PERSONAL = [
        'D13' => 'date_of_birth',
        'D15' => 'place_of_birth',
        'E17' => 'civil_status',
        'E20' => 'civil_status_other',
        'K13' => 'citizenship',
        'D22' => 'height_m',
        'D24' => 'weight_kg',
        'D25' => 'blood_type',
        'D27' => 'umid_id_no',
        'D29' => 'pagibig_id_no',
        'D31' => 'philhealth_no',
        'D32' => 'philsys_card_number',
        'D33' => 'tin_no',
        'D34' => 'agency_employee_no',
        'I17' => 'residential_house_block_lot',
        'L17' => 'residential_street',
        'I19' => 'residential_subdivision',
        'L19' => 'residential_barangay',
        'I22' => 'residential_city',
        'L22' => 'residential_province',
        'I24' => 'residential_zip',
        'I25' => 'permanent_house_block_lot',
        'L25' => 'permanent_street',
        'I27' => 'permanent_subdivision',
        'L27' => 'permanent_barangay',
        'I29' => 'permanent_city',
        'L29' => 'permanent_province',
        'I31' => 'permanent_zip',
        'I32' => 'telephone_no',
        'I33' => 'mobile_no',
        'I34' => 'email_address',
    ];

    /**
     * Section III. One row per level, in the order the form prints them.
     *
     * @var array<string, int>
     */
    private const EDUCATION_ROWS = [
        'elementary' => 55,
        'secondary' => 56,
        'vocational' => 57,
        'college' => 58,
        'graduate' => 59,
    ];

    /**
     * Section III columns, left to right.
     *
     * @var array<string, string>
     */
    private const EDUCATION_COLUMNS = [
        'D' => 'school_name',
        'G' => 'degree_course',
        'J' => 'period_from',
        'K' => 'period_to',
        'L' => 'highest_level_units',
        'M' => 'year_graduated',
        'N' => 'honors',
    ];

    /**
     * Section IV, on the second sheet. Seven printed lines, no more.
     *
     * @var list<int>
     */
    private const ELIGIBILITY_ROWS = [5, 6, 7, 8, 9, 10, 11];

    /**
     * Section IV columns, left to right. The name and the two places span
     * merged cells, so only the leftmost address of each is written.
     *
     * @var array<string, string>
     */
    private const ELIGIBILITY_COLUMNS = [
        'F' => 'rating',
        'I' => 'place_of_examination',
        'L' => 'license_number',
    ];

    /**
     * Section V, on the second sheet: rows 18 to 41.
     *
     * @var list<int>
     */
    private const WORK_ROWS = [
        18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29,
        30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41,
    ];

    /**
     * Section V columns. "From" spans A:B and the two wide columns span
     * D:F and G:I, so only the leftmost address of each is written.
     *
     * @var array<string, string>
     */
    private const WORK_COLUMNS = [
        'D' => 'position_title',
        'G' => 'agency_name',
        'J' => 'monthly_salary',
        'K' => 'salary_grade',
        'L' => 'appointment_status',
    ];

    public function handle(Employee $employee): Spreadsheet
    {
        $book = IOFactory::createReader('Xlsx')->load($this->templatePath());

        $this->fillPersonal($book, $employee);
        $this->fillEducation($book, $employee);
        $this->fillEligibility($book, $employee);
        $this->fillWorkExperience($book, $employee);

        $book->setActiveSheetIndexByName('C1');

        return $book;
    }

    public function templatePath(): string
    {
        return storage_path('app/templates/'.config('ldi.pds.template'));
    }

    private function fillPersonal(Spreadsheet $book, Employee $employee): void
    {
        $sheet = $book->getSheetByName('C1');

        if ($sheet === null) {
            return;
        }

        $pds = $employee->personalDataSheet;

        // The name and sex live on the employee record; everything else in
        // this section is the employee's own to state.
        $fromEmployee = [
            'D10' => $employee->last_name,
            'D11' => $employee->first_name,
            'D12' => $employee->middle_name,
            'N11' => $employee->suffix,
            'E16' => $employee->gender,
        ];

        foreach ($fromEmployee as $cell => $value) {
            $this->write($sheet, $cell, $value);
        }

        if ($pds === null) {
            return;
        }

        $this->write($sheet, 'D13', $pds->date_of_birth?->format('d/m/Y'));

        foreach (self::PERSONAL as $cell => $field) {
            if ($field === 'date_of_birth') {
                continue;
            }

            $this->write($sheet, $cell, $pds->{$field});
        }
    }

    private function fillEducation(Spreadsheet $book, Employee $employee): void
    {
        $sheet = $book->getSheetByName('C1');

        if ($sheet === null) {
            return;
        }

        foreach ($employee->educations as $education) {
            $row = self::EDUCATION_ROWS[$education->level->value];

            foreach (self::EDUCATION_COLUMNS as $column => $field) {
                $this->write($sheet, $column.$row, $education->{$field});
            }
        }
    }

    private function fillEligibility(Spreadsheet $book, Employee $employee): void
    {
        $sheet = $book->getSheetByName('C2');

        if ($sheet === null) {
            return;
        }

        $lines = $employee->eligibilities
            ->sortBy('date_of_examination')
            ->values()
            ->take(count(self::ELIGIBILITY_ROWS));

        foreach ($lines as $index => $line) {
            $row = self::ELIGIBILITY_ROWS[$index];

            $this->write($sheet, 'A'.$row, $line->name());
            $this->write($sheet, 'G'.$row, $line->date_of_examination?->format('d/m/Y'));
            $this->write($sheet, 'M'.$row, $line->date_of_validity?->format('d/m/Y'));

            foreach (self::ELIGIBILITY_COLUMNS as $column => $field) {
                $this->write($sheet, $column.$row, $line->{$field});
            }
        }
    }

    private function fillWorkExperience(Spreadsheet $book, Employee $employee): void
    {
        $sheet = $book->getSheetByName('C2');

        if ($sheet === null) {
            return;
        }

        // The form says to start from the most recent work, which is the
        // order the relation already comes back in.
        $postings = $employee->workExperiences->take(count(self::WORK_ROWS));

        foreach ($postings->values() as $index => $posting) {
            $row = self::WORK_ROWS[$index];

            $this->write($sheet, 'A'.$row, $posting->from_date->format('d/m/Y'));
            $this->write($sheet, 'C'.$row, $posting->endsOn());
            $this->write($sheet, 'M'.$row, $posting->is_government ? 'Y' : 'N');

            foreach (self::WORK_COLUMNS as $column => $field) {
                $this->write($sheet, $column.$row, $posting->{$field});
            }
        }
    }

    private function write(Worksheet $sheet, string $cell, mixed $value): void
    {
        if (blank($value)) {
            return;
        }

        $sheet->setCellValue($cell, (string) $value);
    }
}
