<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ImportEmployeesFromHris extends Command
{
    protected $signature = 'ldi:import-employees';

    protected $description = 'Copy divisions, sections, positions and employees from hris_db';

    /**
     * Maps a source id to the local id, per entity.
     *
     * @var array<string, array<int, int>>
     */
    private array $idMap = [
        'divisions' => [],
        'sections' => [],
        'positions' => [],
        'employees' => [],
    ];

    public function handle(): int
    {
        DB::transaction(function (): void {
            $this->importDivisions();
            $this->importPositions();
            $this->importSections();
            $this->importEmployees();
            $this->importHeadDesignations();
        });

        $this->info(sprintf(
            'Imported %d divisions, %d sections, %d positions, %d employees.',
            count($this->idMap['divisions']),
            count($this->idMap['sections']),
            count($this->idMap['positions']),
            count($this->idMap['employees']),
        ));

        return self::SUCCESS;
    }

    /**
     * Source table names, overridable so tests can point at fixtures.
     *
     * @return array<string, string>
     */
    private function sourceTables(): array
    {
        return config('ldi.hris_tables', [
            'divisions' => 'divisions',
            'sections' => 'sections',
            'positions' => 'positions',
            'employees' => 'employees',
        ]);
    }

    private function source(string $entity): Builder
    {
        return DB::connection(config('ldi.hris_connection', 'hris'))
            ->table($this->sourceTables()[$entity]);
    }

    private function importDivisions(): void
    {
        foreach ($this->source('divisions')->get() as $row) {
            $division = Division::updateOrCreate(
                ['code' => $row->code],
                ['name' => $row->name, 'is_active' => true],
            );

            $this->idMap['divisions'][$row->id] = $division->id;
        }
    }

    private function importPositions(): void
    {
        foreach ($this->source('positions')->get() as $row) {
            $position = Position::updateOrCreate(
                ['title' => $row->title],
                ['item_number' => $row->item_number, 'salary_grade' => $row->salary_grade, 'is_active' => true],
            );

            $this->idMap['positions'][$row->id] = $position->id;
        }
    }

    private function importSections(): void
    {
        foreach ($this->source('sections')->get() as $row) {
            $section = Section::updateOrCreate(
                ['code' => $row->code],
                [
                    'division_id' => $this->idMap['divisions'][$row->division_id] ?? null,
                    'name' => $row->name,
                    'is_active' => true,
                ],
            );

            $this->idMap['sections'][$row->id] = $section->id;
        }
    }

    private function importEmployees(): void
    {
        foreach ($this->source('employees')->whereNull('deleted_at')->get() as $row) {
            $employee = Employee::updateOrCreate(
                ['employee_number' => $row->employee_number],
                [
                    'first_name' => $row->first_name,
                    'middle_name' => $row->middle_name,
                    'last_name' => $row->last_name,
                    'suffix' => $row->suffix,
                    'position_id' => $this->idMap['positions'][$row->position_id] ?? null,
                    'section_id' => $this->idMap['sections'][$row->section_id] ?? null,
                    'date_hired' => $row->date_hired,
                    'employment_status' => $row->employment_status,
                    'is_active' => (bool) $row->is_active,
                ],
            );

            $this->idMap['employees'][$row->id] = $employee->id;
        }
    }

    /**
     * Head designations come last: they point at employees that must exist first.
     */
    private function importHeadDesignations(): void
    {
        foreach ($this->source('divisions')->get() as $row) {
            Division::whereKey($this->idMap['divisions'][$row->id])->update([
                'division_head_employee_id' => $this->idMap['employees'][$row->division_head_employee_id] ?? null,
            ]);
        }

        foreach ($this->source('sections')->get() as $row) {
            Section::whereKey($this->idMap['sections'][$row->id])->update([
                'section_head_employee_id' => $this->idMap['employees'][$row->section_head_employee_id] ?? null,
            ]);
        }
    }
}
