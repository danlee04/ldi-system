<?php

use App\Enums\ApprovalLevel;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use App\Workflow\ApprovalRouter;
use App\Workflow\LdnaConfirmer;

test('the rater agrees with the approval router', function (Closure $arrange) {
    $employee = $arrange();

    $router = app(ApprovalRouter::class);
    $approver = $router->approverFor(ApprovalLevel::SectionHead, $employee)
        ?? $router->approverFor(ApprovalLevel::DivisionHead, $employee);

    expect((new LdnaConfirmer)->confirmerIdFor($employee->fresh()))->toBe($approver?->getKey());
})->with([
    'a section head' => function (): Employee {
        $section = Section::factory()->create();
        $head = Employee::factory()->for($section)->create();
        $section->update(['section_head_employee_id' => $head->id]);

        return Employee::factory()->for($section)->create();
    },
    'the division head when the section has none' => function (): Employee {
        $division = Division::factory()->create();
        $section = Section::factory()->for($division)->create();
        $head = Employee::factory()->for(Section::factory()->for($division))->create();
        $division->update(['division_head_employee_id' => $head->id]);

        return Employee::factory()->for($section)->create();
    },
    'the section head themselves goes up to the division head' => function (): Employee {
        $division = Division::factory()->create();
        $section = Section::factory()->for($division)->create();
        $sectionHead = Employee::factory()->for($section)->create();
        $section->update(['section_head_employee_id' => $sectionHead->id]);
        $divisionHead = Employee::factory()->for(Section::factory()->for($division))->create();
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        return $sectionHead;
    },
    'an inactive section head is passed over' => function (): Employee {
        $division = Division::factory()->create();
        $section = Section::factory()->for($division)->create();
        $gone = Employee::factory()->for($section)->inactive()->create();
        $section->update(['section_head_employee_id' => $gone->id]);
        $divisionHead = Employee::factory()->for(Section::factory()->for($division))->create();
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        return Employee::factory()->for($section)->create();
    },
    'nobody at all' => fn (): Employee => Employee::factory()->create(),
]);

test('hr rates whoever has nobody above them', function () {
    $employee = Employee::factory()->create();

    expect((new LdnaConfirmer)->confirms(User::factory()->hr()->create(), $employee))->toBeTrue()
        ->and((new LdnaConfirmer)->confirms(User::factory()->employee()->create(), $employee))->toBeFalse();
});

test('hr does not rate somebody a head rates', function () {
    $section = Section::factory()->create();
    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);
    $employee = Employee::factory()->for($section)->create();

    expect((new LdnaConfirmer)->confirms($headUser, $employee))->toBeTrue()
        ->and((new LdnaConfirmer)->confirms(User::factory()->hr()->create(), $employee))->toBeFalse();
});

test('nobody rates themselves, hr included', function () {
    $hrUser = User::factory()->hr()->create();
    $hrEmployee = Employee::factory()->create(['user_id' => $hrUser->id]);

    expect((new LdnaConfirmer)->confirms($hrUser, $hrEmployee))->toBeFalse();
});
