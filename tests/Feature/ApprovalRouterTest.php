<?php

use App\Enums\ApprovalLevel;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Workflow\ApprovalRouter;

test('it starts at the section head when the section has one', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBe(ApprovalLevel::SectionHead);
});

test('it skips to the division head when the section has no head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create(['section_head_employee_id' => null]);
    $divisionHead = Employee::factory()->for($section)->create();
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBe(ApprovalLevel::DivisionHead);
});

test('it returns nothing when neither level has a head', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBeNull();
});

test('an employee never approves their own record', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    expect(app(ApprovalRouter::class)->firstLevelFor($head))->toBeNull();
});

test('a section head submitting their own record skips to the division head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $sectionHead = Employee::factory()->for($section)->create();
    $divisionHead = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $sectionHead->id]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    expect(app(ApprovalRouter::class)->firstLevelFor($sectionHead))->toBe(ApprovalLevel::DivisionHead);
});

test('after the section head comes the division head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $divisionHead = Employee::factory()->for($section)->create();
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::SectionHead, $employee))
        ->toBe(ApprovalLevel::DivisionHead);
});

test('after the section head comes nothing when the division has no head', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::SectionHead, $employee))->toBeNull();
});

test('the division head is the last level', function () {
    $employee = Employee::factory()->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::DivisionHead, $employee))->toBeNull();
});

test('an inactive head cannot approve', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->inactive()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBeNull();
});
