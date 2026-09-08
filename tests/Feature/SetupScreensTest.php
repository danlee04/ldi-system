<?php

use App\Enums\ApprovalLevel;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use App\Workflow\ApprovalRouter;
use Livewire\Livewire;

test('hr can designate a section head', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $head = Employee::factory()->for($section)->create();

    Livewire::test('pages::setup.sections')
        ->call('edit', $section->id)
        ->set('sectionHeadEmployeeId', $head->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($section->fresh()->section_head_employee_id)->toBe($head->id);
});

test('hr can designate a division head', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create();
    $head = Employee::factory()->for(Section::factory()->for($division))->create();

    Livewire::test('pages::setup.divisions')
        ->call('edit', $division->id)
        ->set('divisionHeadEmployeeId', $head->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($division->fresh()->division_head_employee_id)->toBe($head->id);
});

test('hr can add a position', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::setup.positions')
        ->call('create')
        ->set('title', 'Administrative Officer V')
        ->set('salaryGrade', 18)
        ->call('save')
        ->assertHasNoErrors();

    expect(Position::where('title', 'Administrative Officer V')->first()->salary_grade)->toBe(18);
});

test('a section head cannot open setup', function () {
    $this->actingAs(User::factory()->sectionHead()->create());

    $this->get(route('setup.sections'))->assertForbidden();
});

test('the section list shows which sections still have no head', function () {
    $this->actingAs(User::factory()->hr()->create());

    Section::factory()->create(['name' => 'Headless Section', 'section_head_employee_id' => null]);

    Livewire::test('pages::setup.sections')
        ->assertSee('Headless Section')
        ->assertSee('No head');
});

test('designating a head unblocks a stuck submission', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    $head = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBeNull();

    Livewire::test('pages::setup.sections')
        ->call('edit', $section->id)
        ->set('sectionHeadEmployeeId', $head->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee->fresh()))
        ->toBe(ApprovalLevel::SectionHead);
});

test('the section search narrows by name and code', function () {
    $this->actingAs(User::factory()->hr()->create());

    Section::factory()->create(['name' => 'Human Resource Section', 'code' => 'HRS']);
    Section::factory()->create(['name' => 'Budget Section', 'code' => 'BUDG']);

    Livewire::test('pages::setup.sections')
        ->set('search', 'HRS')
        ->assertSee('Human Resource Section')
        ->assertDontSee('Budget Section');
});

test('the section division filter narrows the list', function () {
    $this->actingAs(User::factory()->hr()->create());

    $wanted = Division::factory()->create();
    Section::factory()->for($wanted)->create(['name' => 'Human Resource Section']);
    Section::factory()->create(['name' => 'Budget Section']);

    Livewire::test('pages::setup.sections')
        ->set('filterDivisionId', $wanted->id)
        ->assertSee('Human Resource Section')
        ->assertDontSee('Budget Section');
});

test('the section list paginates', function () {
    $this->actingAs(User::factory()->hr()->create());

    Section::factory()->count(20)->create();

    $sections = Livewire::test('pages::setup.sections')->instance()->sections;

    expect($sections->count())->toBe(15)
        ->and($sections->total())->toBe(20);
});

test('the position search narrows by title and item number', function () {
    $this->actingAs(User::factory()->hr()->create());

    Position::factory()->create(['title' => 'Administrative Officer V', 'item_number' => 'ITEM-1']);
    Position::factory()->create(['title' => 'Nurse II', 'item_number' => 'ITEM-2']);

    Livewire::test('pages::setup.positions')
        ->set('search', 'Administrative')
        ->assertSee('Administrative Officer V')
        ->assertDontSee('Nurse II');
});

test('the position salary grade filter narrows the list', function () {
    $this->actingAs(User::factory()->hr()->create());

    Position::factory()->create(['title' => 'Administrative Officer V', 'salary_grade' => 18]);
    Position::factory()->create(['title' => 'Nurse II', 'salary_grade' => 16]);

    Livewire::test('pages::setup.positions')
        ->set('filterSalaryGrade', 18)
        ->assertSee('Administrative Officer V')
        ->assertDontSee('Nurse II');
});

test('the salary grade filter offers only the grades in use', function () {
    $this->actingAs(User::factory()->hr()->create());

    Position::factory()->create(['salary_grade' => 18]);
    Position::factory()->create(['salary_grade' => 18]);
    Position::factory()->create(['salary_grade' => 16]);
    Position::factory()->create(['salary_grade' => null]);

    $grades = Livewire::test('pages::setup.positions')->instance()->salaryGrades;

    expect($grades->all())->toBe([16, 18]);
});

test('a section code cannot collide with another section', function () {
    $this->actingAs(User::factory()->hr()->create());

    Section::factory()->create(['code' => 'HRS']);
    $section = Section::factory()->create(['code' => 'BUDG']);

    Livewire::test('pages::setup.sections')
        ->call('edit', $section->id)
        ->set('code', 'HRS')
        ->call('save')
        ->assertHasErrors('code');
});
