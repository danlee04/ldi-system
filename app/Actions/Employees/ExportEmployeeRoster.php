<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\EmployeeEligibility;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The roster as a spreadsheet, for the reports and the memos that are
 * still written outside this system.
 */
class ExportEmployeeRoster
{
    /**
     * Stream the given roster as a CSV.
     *
     * The query arrives already filtered and already scoped to what the
     * signed-in user may see, so whoever downloads this gets the list
     * they were looking at and nothing more.
     *
     * @param  Builder<Employee>  $roster
     */
    public function handle(Builder $roster, int $year): StreamedResponse
    {
        $file = 'employees-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($roster, $year): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // Excel reads a CSV in the machine's own codepage unless the
            // file opens with this mark. Without it every ñ and é in the
            // roster arrives mangled.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'last_name', 'first_name', 'middle_name', 'suffix', 'gender', 'item_number',
                'division', 'section', 'position', 'employment_status', 'date_hired',
                'cpd_units_'.$year, 'eligibility', 'eligibility_expires_on',
            ]);

            foreach ($roster->get() as $employee) {
                fputcsv($out, $this->line($employee));
            }

            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string|int|null>
     */
    private function line(Employee $employee): array
    {
        return [
            $employee->last_name,
            $employee->first_name,
            $employee->middle_name,
            $employee->suffix,
            $employee->gender,
            $employee->item_number,
            $employee->division?->name,
            $employee->section?->name,
            $employee->position?->title,
            $employee->employment_status->label(),
            $employee->date_hired?->toDateString(),
            (int) ($employee->cpd_units_for_year ?? 0),
            $this->eligibilities($employee),
            $employee->eligibilityExpiresOn()?->toDateString(),
        ];
    }

    /**
     * Every eligibility, not the roster column's "first one plus a count"
     * — a spreadsheet has room for all of them and is sorted on them.
     */
    private function eligibilities(Employee $employee): string
    {
        return $employee->eligibilities
            ->map(fn (EmployeeEligibility $eligibility): string => $eligibility->name())
            ->filter()
            ->implode('; ');
    }
}
