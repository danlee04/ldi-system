<?php

use App\Actions\Pds\FillPersonalDataSheet;
use App\Enums\EducationLevel;
use App\Enums\LdType;
use App\Enums\OtherInformationType;
use App\Enums\TrainingStatus;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\EmployeeChild;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEligibility;
use App\Models\EmployeeOtherInformation;
use App\Models\EmployeeReference;
use App\Models\EmployeeVoluntaryWork;
use App\Models\EmployeeWorkExperience;
use App\Models\PersonalDataSheet;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Signs in an employee and hands back their record.
 */
function pdsEmployee(array $attributes = []): Employee
{
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create([...$attributes, 'user_id' => $user->id]);

    test()->actingAs($user);

    return $employee;
}

test('an employee fills in their own section I', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.date_of_birth', '1990-04-15')
        ->set('form.place_of_birth', 'Butuan City')
        ->set('form.civil_status', 'Married')
        ->set('form.citizenship', 'Filipino')
        ->set('form.blood_type', 'O+')
        ->set('form.mobile_no', '09171234567')
        ->set('form.email_address', 'maria@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $sheet = $employee->fresh()->personalDataSheet;

    expect($sheet)->not->toBeNull()
        ->and($sheet->date_of_birth->toDateString())->toBe('1990-04-15')
        ->and($sheet->place_of_birth)->toBe('Butuan City')
        ->and($sheet->civil_status)->toBe('Married');
});

test('saving twice keeps one sheet', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')->set('form.blood_type', 'A+')->call('save');
    Livewire::test('pages::my-pds')->set('form.blood_type', 'B+')->call('save');

    expect(PersonalDataSheet::count())->toBe(1)
        ->and($employee->fresh()->personalDataSheet->blood_type)->toBe('B+');
});

test('choosing Others makes the employee say what it is', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.civil_status', 'Others')
        ->set('form.civil_status_other', '')
        ->call('save')
        ->assertHasErrors('form.civil_status_other');
});

test('copying the residential address fills the permanent one', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.residential_house_block_lot', '12')
        ->set('form.residential_barangay', 'Ampayon')
        ->set('form.residential_city', 'Butuan City')
        ->call('copyResidentialToPermanent')
        ->assertSet('form.permanent_house_block_lot', '12')
        ->assertSet('form.permanent_barangay', 'Ampayon')
        ->assertSet('form.permanent_city', 'Butuan City');
});

test('an account with no employee record cannot open the page', function () {
    $this->actingAs(User::factory()->employee()->create());

    $this->get(route('my-pds'))->assertForbidden();
});

test('completeness reports how much of section I is filled', function () {
    $employee = pdsEmployee();

    expect(Livewire::test('pages::my-pds')->instance()->completeness)->toBe(0);

    PersonalDataSheet::factory()->create(['employee_id' => $employee->id]);

    expect($employee->fresh()->personalDataSheet->completeness())->toBeGreaterThan(80);
});

test('the download is the CSC workbook with section I written into it', function () {
    $employee = pdsEmployee([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Cruz',
        'suffix' => null,
        'gender' => 'Female',
    ]);

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'date_of_birth' => '1990-04-15',
        'place_of_birth' => 'Butuan City',
        'civil_status' => 'Married',
        'citizenship' => 'Filipino',
        'blood_type' => 'O+',
        'residential_city' => 'Butuan City',
        'mobile_no' => '09171234567',
    ]);

    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());
    $sheet = $book->getSheetByName('C1');

    expect($sheet->getCell('D10')->getValue())->toBe('Cruz')
        ->and($sheet->getCell('D11')->getValue())->toBe('Maria')
        ->and($sheet->getCell('D12')->getValue())->toBe('Santos')
        ->and($sheet->getCell('D13')->getValue())->toBe('15/04/1990')
        ->and($sheet->getCell('D15')->getValue())->toBe('Butuan City')
        ->and($sheet->getCell('D25')->getValue())->toBe('O+')
        ->and($sheet->getCell('I22')->getValue())->toBe('Butuan City')
        ->and($sheet->getCell('I33')->getValue())->toBe('09171234567');
});

test('the template itself is never written to', function () {
    $employee = pdsEmployee();
    $path = app(FillPersonalDataSheet::class)->templatePath();
    $before = md5_file($path);

    PersonalDataSheet::factory()->create(['employee_id' => $employee->id]);
    app(FillPersonalDataSheet::class)->handle($employee->fresh());

    expect(md5_file($path))->toBe($before);
});

test('an employee downloads their own filled workbook', function () {
    $employee = pdsEmployee(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Cruz']);

    PersonalDataSheet::factory()->create(['employee_id' => $employee->id]);

    $this->get(route('my-pds.download'))
        ->assertOk()
        ->assertDownload('cruz-maria-pds.xlsx');
});

test('a section head cannot download the PDS of somebody outside their section', function () {
    $section = Section::factory()->create();
    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $this->actingAs($headUser);

    $this->get(route('employees.pds', Employee::factory()->create()))->assertForbidden();
    $this->get(route('employees.pds', $head))->assertOk();
});

test('an unfilled sheet still produces a usable blank form', function () {
    $employee = pdsEmployee(['first_name' => 'Jose', 'middle_name' => null, 'last_name' => 'Rizal']);

    $book = app(FillPersonalDataSheet::class)->handle($employee);
    $sheet = $book->getSheetByName('C1');

    expect($sheet->getCell('D10')->getValue())->toBe('Rizal')
        ->and($sheet->getCell('D13')->getValue())->toBeEmpty();
});

test('the workbook is not readable by the wrong person through a guessed URL', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('employees.pds', Employee::factory()->create()))->assertForbidden();
});

test('the filled workbook still opens as a spreadsheet', function () {
    $employee = pdsEmployee();
    PersonalDataSheet::factory()->create(['employee_id' => $employee->id]);

    $path = tempnam(sys_get_temp_dir(), 'pds').'.xlsx';
    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());
    (new Xlsx($book))->save($path);

    $reopened = IOFactory::createReader('Xlsx')->load($path);

    expect($reopened->getSheetByName('C1'))->not->toBeNull()
        ->and($reopened->getSheetCount())->toBe(11);

    unlink($path);
});

test('an employee records their education level by level', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('education.college.school_name', 'Caraga State University')
        ->set('education.college.degree_course', 'BS Information Technology')
        ->set('education.college.period_from', 2010)
        ->set('education.college.period_to', 2014)
        ->set('education.college.year_graduated', 2014)
        ->set('education.college.honors', 'Cum Laude')
        ->call('saveEducation')
        ->assertHasNoErrors();

    $college = $employee->fresh()->educations->firstWhere('level', EducationLevel::College);

    expect($college)->not->toBeNull()
        ->and($college->school_name)->toBe('Caraga State University')
        ->and($college->year_graduated)->toBe(2014)
        ->and($college->honors)->toBe('Cum Laude');
});

test('a level left blank leaves no row behind', function () {
    $employee = pdsEmployee();

    EmployeeEducation::factory()->create([
        'employee_id' => $employee->id,
        'level' => EducationLevel::Graduate,
        'school_name' => 'Entered By Mistake',
    ]);

    Livewire::test('pages::my-pds')
        ->set('education.graduate.school_name', '')
        ->set('education.graduate.degree_course', '')
        ->set('education.graduate.period_from', '')
        ->set('education.graduate.period_to', '')
        ->set('education.graduate.highest_level_units', '')
        ->set('education.graduate.year_graduated', '')
        ->set('education.graduate.honors', '')
        ->call('saveEducation');

    expect($employee->fresh()->educations()->where('level', EducationLevel::Graduate)->exists())->toBeFalse();
});

test('the period cannot end before it starts', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('education.college.school_name', 'Caraga State University')
        ->set('education.college.period_from', 2014)
        ->set('education.college.period_to', 2010)
        ->call('saveEducation')
        ->assertHasErrors('education.college.period_to');
});

test('saving education twice keeps one row per level', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')->set('education.college.school_name', 'First')->call('saveEducation');
    Livewire::test('pages::my-pds')->set('education.college.school_name', 'Second')->call('saveEducation');

    expect($employee->fresh()->educations)->toHaveCount(1)
        ->and($employee->fresh()->educations->first()->school_name)->toBe('Second');
});

test('education lands on the right line of the workbook', function () {
    $employee = pdsEmployee();

    EmployeeEducation::factory()->create([
        'employee_id' => $employee->id,
        'level' => EducationLevel::Elementary,
        'school_name' => 'Ampayon Elementary School',
        'degree_course' => null,
        'period_from' => 1996,
        'period_to' => 2002,
        'year_graduated' => 2002,
        'honors' => null,
    ]);

    EmployeeEducation::factory()->create([
        'employee_id' => $employee->id,
        'level' => EducationLevel::College,
        'school_name' => 'Caraga State University',
        'degree_course' => 'BS Information Technology',
        'period_from' => 2010,
        'period_to' => 2014,
        'year_graduated' => 2014,
        'honors' => 'Cum Laude',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C1');

    // Row 55 is the elementary line, row 58 the college line.
    expect($sheet->getCell('D55')->getValue())->toBe('Ampayon Elementary School')
        ->and($sheet->getCell('J55')->getValue())->toEqual(1996)
        ->and($sheet->getCell('M55')->getValue())->toEqual(2002)
        ->and($sheet->getCell('D58')->getValue())->toBe('Caraga State University')
        ->and($sheet->getCell('G58')->getValue())->toBe('BS Information Technology')
        ->and($sheet->getCell('N58')->getValue())->toBe('Cum Laude')
        ->and($sheet->getCell('D56')->getValue())->toBeEmpty();
});

test('an employee records a civil service eligibility', function () {
    $employee = pdsEmployee();
    $eligibility = Eligibility::factory()->create(['name' => 'CSP - Career Service Professional']);

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.eligibility_id', $eligibility->id)
        ->set('eligibilities.0.rating', '86.45')
        ->set('eligibilities.0.date_of_examination', '2015-03-15')
        ->set('eligibilities.0.place_of_examination', 'Butuan City')
        ->call('saveEligibilities')
        ->assertHasNoErrors();

    $line = $employee->fresh()->eligibilities->first();

    expect($line)->not->toBeNull()
        ->and($line->eligibility_id)->toBe($eligibility->id)
        ->and($line->name())->toBe('CSP - Career Service Professional')
        ->and($line->rating)->toBe('86.45')
        ->and($line->date_of_examination->toDateString())->toBe('2015-03-15');
});

test('an eligibility not on the list can be written in full', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.detail', 'Registered Nurse, PRC')
        ->set('eligibilities.0.license_number', '0123456')
        ->set('eligibilities.0.date_of_validity', today()->addYears(2)->toDateString())
        ->call('saveEligibilities')
        ->assertHasNoErrors();

    expect($employee->fresh()->eligibilities->first()->name())->toBe('Registered Nurse, PRC');
});

test('a nameless line is not saved', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.rating', '80')
        ->call('saveEligibilities')
        ->assertHasNoErrors();

    expect($employee->fresh()->eligibilities)->toBeEmpty();
});

test('an eligibility cannot lapse before it was taken', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.detail', 'Registered Nurse, PRC')
        ->set('eligibilities.0.date_of_examination', '2020-06-01')
        ->set('eligibilities.0.date_of_validity', '2019-06-01')
        ->call('saveEligibilities')
        ->assertHasErrors('eligibilities.0.date_of_validity');
});

test('removing a line and saving takes it off the record', function () {
    $employee = pdsEmployee();

    EmployeeEligibility::factory()->for($employee)->create(['detail' => 'Entered By Mistake']);

    Livewire::test('pages::my-pds')
        ->call('removeRow', 'eligibilities', 0)
        ->call('saveEligibilities');

    expect($employee->fresh()->eligibilities)->toBeEmpty();
});

test('saving twice edits the same line rather than adding another', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.detail', 'First')
        ->call('saveEligibilities')
        ->set('eligibilities.0.detail', 'Second')
        ->call('saveEligibilities');

    expect($employee->fresh()->eligibilities)->toHaveCount(1)
        ->and($employee->fresh()->eligibilities->first()->detail)->toBe('Second');
});

test('an employee cannot edit somebody elses eligibility line', function () {
    $employee = pdsEmployee();
    $stranger = EmployeeEligibility::factory()->create(['detail' => 'Not Yours']);

    Livewire::test('pages::my-pds')
        ->set('eligibilities.0.id', $stranger->id)
        ->set('eligibilities.0.detail', 'Stolen')
        ->call('saveEligibilities');

    expect($stranger->fresh()->detail)->toBe('Not Yours')
        ->and($employee->fresh()->eligibilities->first()->detail)->toBe('Stolen');
});

test('eligibility lands on the right lines of the workbook', function () {
    $employee = pdsEmployee();

    EmployeeEligibility::factory()->for($employee)->create([
        'eligibility_id' => null,
        'detail' => 'CSP - Career Service Professional',
        'rating' => '86.45',
        'date_of_examination' => '2015-03-15',
        'place_of_examination' => 'Butuan City',
        'license_number' => null,
        'date_of_validity' => null,
    ]);

    EmployeeEligibility::factory()->for($employee)->create([
        'eligibility_id' => null,
        'detail' => 'Registered Nurse, PRC',
        'rating' => '81.20',
        'date_of_examination' => '2018-11-20',
        'place_of_examination' => 'Cagayan de Oro City',
        'license_number' => '0123456',
        'date_of_validity' => '2027-11-20',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C2');

    // Rows 5 to 11 are the seven printed lines, oldest examination first.
    expect($sheet->getCell('A5')->getValue())->toBe('CSP - Career Service Professional')
        ->and($sheet->getCell('F5')->getValue())->toEqual('86.45')
        ->and($sheet->getCell('G5')->getValue())->toBe('15/03/2015')
        ->and($sheet->getCell('I5')->getValue())->toBe('Butuan City')
        ->and($sheet->getCell('M5')->getValue())->toBeEmpty()
        ->and($sheet->getCell('A6')->getValue())->toBe('Registered Nurse, PRC')
        ->and($sheet->getCell('L6')->getValue())->toEqual('0123456')
        ->and($sheet->getCell('M6')->getValue())->toBe('20/11/2027')
        ->and($sheet->getCell('A7')->getValue())->toBeEmpty();
});

test('an employee records a posting', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('work.0.position_title', 'Administrative Assistant II')
        ->set('work.0.agency_name', 'Department of Health')
        ->set('work.0.from_date', '2018-06-01')
        ->set('work.0.to_date', '2022-05-31')
        ->set('work.0.monthly_salary', '24500')
        ->set('work.0.salary_grade', '11-1')
        ->set('work.0.appointment_status', 'Permanent')
        ->set('work.0.is_government', true)
        ->call('saveWork')
        ->assertHasNoErrors();

    $posting = $employee->fresh()->workExperiences->first();

    expect($posting)->not->toBeNull()
        ->and($posting->position_title)->toBe('Administrative Assistant II')
        ->and($posting->from_date->toDateString())->toBe('2018-06-01')
        ->and($posting->is_government)->toBeTrue()
        ->and($posting->endsOn())->toBe('31/05/2022');
});

test('the post they still hold prints as Present', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('work.0.position_title', 'Nurse II')
        ->set('work.0.agency_name', 'DOH Treatment and Rehabilitation Center Caraga')
        ->set('work.0.from_date', '2022-06-01')
        ->set('work.0.to_date', '')
        ->call('saveWork')
        ->assertHasNoErrors();

    expect($employee->fresh()->workExperiences->first()->endsOn())->toBe('Present');
});

test('a half-typed posting is refused rather than dropped', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('work.0.position_title', 'Nurse II')
        ->call('saveWork')
        ->assertHasErrors(['work.0.from_date', 'work.0.agency_name']);

    expect($employee->fresh()->workExperiences)->toBeEmpty();
});

test('an untouched line saves nothing and raises nothing', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->call('saveWork')
        ->assertHasNoErrors();

    expect($employee->fresh()->workExperiences)->toBeEmpty();
});

test('a posting cannot end before it started', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('work.0.position_title', 'Nurse II')
        ->set('work.0.agency_name', 'Department of Health')
        ->set('work.0.from_date', '2022-06-01')
        ->set('work.0.to_date', '2021-06-01')
        ->call('saveWork')
        ->assertHasErrors('work.0.to_date');
});

test('removing a posting and saving takes it off the record', function () {
    $employee = pdsEmployee();

    EmployeeWorkExperience::factory()->for($employee)->create(['position_title' => 'Entered By Mistake']);

    Livewire::test('pages::my-pds')
        ->call('removeRow', 'work', 0)
        ->call('saveWork');

    expect($employee->fresh()->workExperiences)->toBeEmpty();
});

test('an employee cannot edit somebody elses posting', function () {
    $employee = pdsEmployee();
    $stranger = EmployeeWorkExperience::factory()->create(['position_title' => 'Not Yours']);

    Livewire::test('pages::my-pds')
        ->set('work.0.id', $stranger->id)
        ->set('work.0.position_title', 'Stolen')
        ->set('work.0.agency_name', 'Department of Health')
        ->set('work.0.from_date', '2020-01-01')
        ->call('saveWork');

    expect($stranger->fresh()->position_title)->toBe('Not Yours')
        ->and($employee->fresh()->workExperiences->first()->position_title)->toBe('Stolen');
});

test('work experience lands on the right lines of the workbook, most recent first', function () {
    $employee = pdsEmployee();

    EmployeeWorkExperience::factory()->for($employee)->create([
        'from_date' => '2015-03-02',
        'to_date' => '2022-05-31',
        'position_title' => 'Administrative Assistant II',
        'agency_name' => 'Department of Health',
        'monthly_salary' => '24500',
        'salary_grade' => '11-1',
        'appointment_status' => 'Permanent',
        'is_government' => true,
    ]);

    EmployeeWorkExperience::factory()->for($employee)->current()->create([
        'from_date' => '2022-06-01',
        'position_title' => 'Nurse II',
        'agency_name' => 'DOH TRC Caraga',
        'monthly_salary' => '39000',
        'salary_grade' => '15-2',
        'appointment_status' => 'Permanent',
        'is_government' => true,
    ]);

    EmployeeWorkExperience::factory()->for($employee)->create([
        'from_date' => '2012-01-05',
        'to_date' => '2015-02-27',
        'position_title' => 'Encoder',
        'agency_name' => 'Caraga Data Services',
        'monthly_salary' => '12000',
        'salary_grade' => null,
        'appointment_status' => 'Contract of Service',
        'is_government' => false,
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C2');

    // Rows 18 onwards, newest posting first.
    expect($sheet->getCell('D18')->getValue())->toBe('Nurse II')
        ->and($sheet->getCell('A18')->getValue())->toBe('01/06/2022')
        ->and($sheet->getCell('C18')->getValue())->toBe('Present')
        ->and($sheet->getCell('M18')->getValue())->toBe('Y')
        ->and($sheet->getCell('D19')->getValue())->toBe('Administrative Assistant II')
        ->and($sheet->getCell('C19')->getValue())->toBe('31/05/2022')
        ->and($sheet->getCell('G19')->getValue())->toBe('Department of Health')
        ->and($sheet->getCell('K19')->getValue())->toEqual('11-1')
        ->and($sheet->getCell('L19')->getValue())->toBe('Permanent')
        ->and($sheet->getCell('D20')->getValue())->toBe('Encoder')
        ->and($sheet->getCell('M20')->getValue())->toBe('N')
        ->and($sheet->getCell('D21')->getValue())->toBeEmpty();
});

test('an employee fills in their family background', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.spouse_last_name', 'Cruz')
        ->set('form.spouse_first_name', 'Jose')
        ->set('form.spouse_occupation', 'Teacher')
        ->set('form.father_last_name', 'Santos')
        ->set('form.father_first_name', 'Pedro')
        ->set('form.mother_last_name', 'Reyes')
        ->set('form.mother_first_name', 'Ana')
        ->call('saveFamily')
        ->assertHasNoErrors();

    $sheet = $employee->fresh()->personalDataSheet;

    expect($sheet->spouse_last_name)->toBe('Cruz')
        ->and($sheet->father_first_name)->toBe('Pedro')
        ->and($sheet->mother_last_name)->toBe('Reyes');
});

test('saving the family background leaves section I alone', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'blood_type' => 'O+',
    ]);

    Livewire::test('pages::my-pds')
        ->set('form.father_last_name', 'Santos')
        ->call('saveFamily');

    expect($employee->fresh()->personalDataSheet->blood_type)->toBe('O+');
});

test('an employee lists their children', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('children.0.full_name', 'Maria Clara S. Cruz')
        ->set('children.0.date_of_birth', '2015-08-09')
        ->call('saveFamily')
        ->assertHasNoErrors();

    $child = $employee->fresh()->children->first();

    expect($child->full_name)->toBe('Maria Clara S. Cruz')
        ->and($child->date_of_birth->toDateString())->toBe('2015-08-09');
});

test('a birthday with no name behind it is refused', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('children.0.date_of_birth', '2015-08-09')
        ->call('saveFamily')
        ->assertHasErrors('children.0.full_name');
});

test('family background lands on the right cells of the workbook', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'spouse_last_name' => 'Cruz',
        'spouse_first_name' => 'Jose',
        'spouse_middle_name' => 'Rizal',
        'spouse_suffix' => 'Jr.',
        'spouse_occupation' => 'Teacher',
        'father_last_name' => 'Santos',
        'father_first_name' => 'Pedro',
        'mother_last_name' => 'Reyes',
        'mother_first_name' => 'Ana',
    ]);

    EmployeeChild::factory()->for($employee)->create([
        'full_name' => 'Maria Clara S. Cruz',
        'date_of_birth' => '2015-08-09',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C1');

    expect($sheet->getCell('D36')->getValue())->toBe('Cruz')
        ->and($sheet->getCell('D37')->getValue())->toBe('Jose')
        ->and($sheet->getCell('H37')->getValue())->toBe('Jr.')
        ->and($sheet->getCell('D38')->getValue())->toBe('Rizal')
        ->and($sheet->getCell('D39')->getValue())->toBe('Teacher')
        ->and($sheet->getCell('D44')->getValue())->toBe('Santos')
        ->and($sheet->getCell('D48')->getValue())->toBe('Reyes')
        ->and($sheet->getCell('I37')->getValue())->toBe('Maria Clara S. Cruz')
        ->and($sheet->getCell('M37')->getValue())->toBe('09/08/2015');
});

test('an employee records voluntary work', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('voluntary.0.organization', 'Philippine Red Cross, Butuan City Chapter')
        ->set('voluntary.0.from_date', '2019-01-05')
        ->set('voluntary.0.to_date', '2019-12-20')
        ->set('voluntary.0.hours', '120')
        ->set('voluntary.0.position', 'Volunteer')
        ->call('saveVoluntary')
        ->assertHasNoErrors();

    $entry = $employee->fresh()->voluntaryWorks->first();

    expect($entry->organization)->toBe('Philippine Red Cross, Butuan City Chapter')
        ->and($entry->hours)->toBe(120);
});

test('voluntary work without an organisation is refused', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('voluntary.0.from_date', '2019-01-05')
        ->call('saveVoluntary')
        ->assertHasErrors('voluntary.0.organization');
});

test('voluntary work lands on the right lines of the workbook', function () {
    $employee = pdsEmployee();

    EmployeeVoluntaryWork::factory()->for($employee)->create([
        'organization' => 'Philippine Red Cross, Butuan City Chapter',
        'from_date' => '2019-01-05',
        'to_date' => '2019-12-20',
        'hours' => 120,
        'position' => 'Volunteer',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C3');

    expect($sheet->getCell('A27')->getValue())->toBe('Philippine Red Cross, Butuan City Chapter')
        ->and($sheet->getCell('E27')->getValue())->toBe('05/01/2019')
        ->and($sheet->getCell('F27')->getValue())->toBe('20/12/2019')
        ->and($sheet->getCell('G27')->getValue())->toEqual(120)
        ->and($sheet->getCell('H27')->getValue())->toBe('Volunteer')
        ->and($sheet->getCell('A28')->getValue())->toBeEmpty();
});

test('an employee fills the three lists of section VIII', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('other.skill.0', 'Photography')
        ->set('other.skill.1', 'Public speaking')
        ->set('other.distinction.0', 'Outstanding Employee 2024')
        ->set('other.membership.0', 'Philippine Nurses Association')
        ->call('saveOther')
        ->assertHasNoErrors();

    $lines = $employee->fresh()->otherInformation;

    expect($lines)->toHaveCount(4)
        ->and($lines->where('type', OtherInformationType::Skill)->pluck('description')->all())
        ->toBe(['Photography', 'Public speaking']);
});

test('clearing a line of section VIII removes it', function () {
    $employee = pdsEmployee();

    EmployeeOtherInformation::factory()->for($employee)->create(['description' => 'Entered By Mistake']);

    Livewire::test('pages::my-pds')
        ->set('other.skill.0', '')
        ->call('saveOther');

    expect($employee->fresh()->otherInformation)->toBeEmpty();
});

test('other information lands in the right three columns of the workbook', function () {
    $employee = pdsEmployee();

    EmployeeOtherInformation::factory()->for($employee)->create([
        'type' => OtherInformationType::Skill,
        'description' => 'Photography',
    ]);

    EmployeeOtherInformation::factory()->for($employee)->create([
        'type' => OtherInformationType::Distinction,
        'description' => 'Outstanding Employee 2024',
    ]);

    EmployeeOtherInformation::factory()->for($employee)->create([
        'type' => OtherInformationType::Membership,
        'description' => 'Philippine Nurses Association',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C3');

    expect($sheet->getCell('A39')->getValue())->toBe('Photography')
        ->and($sheet->getCell('C39')->getValue())->toBe('Outstanding Employee 2024')
        ->and($sheet->getCell('I39')->getValue())->toBe('Philippine Nurses Association');
});

test('approved training fills section VI, newest first', function () {
    $employee = pdsEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'title' => 'Gender and Development Orientation',
        'date_start' => '2024-03-04',
        'date_end' => '2024-03-06',
        'hours' => 24,
        'ld_type' => LdType::Foundation,
        'conducted_by' => 'Civil Service Commission',
    ]);

    TrainingRecord::factory()->for($employee)->approved()->create([
        'title' => 'Basic Life Support Training',
        'date_start' => '2025-07-14',
        'date_end' => '2025-07-15',
        'hours' => 16,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Philippine Red Cross',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C3');

    expect($sheet->getCell('A5')->getValue())->toBe('Basic Life Support Training')
        ->and($sheet->getCell('E5')->getValue())->toBe('14/07/2025')
        ->and($sheet->getCell('F5')->getValue())->toBe('15/07/2025')
        ->and($sheet->getCell('G5')->getValue())->toEqual(16)
        ->and($sheet->getCell('H5')->getValue())->toBe('Technical')
        ->and($sheet->getCell('I5')->getValue())->toBe('Philippine Red Cross')
        ->and($sheet->getCell('A6')->getValue())->toBe('Gender and Development Orientation')
        ->and($sheet->getCell('A7')->getValue())->toBeEmpty();
});

test('training still waiting on a decision stays off the form', function () {
    $employee = pdsEmployee();

    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Not Approved Yet',
        'status' => TrainingStatus::Pending,
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C3');

    expect($sheet->getCell('A5')->getValue())->toBeEmpty();
});

test('a repeater name from the browser cannot reach another property', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->call('addRow', 'form')
        ->assertNotFound();
});

test('training past the seventeenth line carries on to the continuation sheet', function () {
    $employee = pdsEmployee();

    // Newest first, so the eighteenth line is the oldest of the nineteen.
    foreach (range(1, 19) as $offset) {
        TrainingRecord::factory()->for($employee)->approved()->create([
            'title' => 'Training '.$offset,
            'date_start' => today()->subMonths($offset),
            'date_end' => today()->subMonths($offset),
        ]);
    }

    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());

    expect($book->getSheetByName('C3')->getCell('A5')->getValue())->toBe('Training 1')
        ->and($book->getSheetByName('C3')->getCell('A21')->getValue())->toBe('Training 17')
        ->and($book->getSheetByName('C5_L&D cont.')->getCell('A6')->getValue())->toBe('Training 18')
        ->and($book->getSheetByName('C5_L&D cont.')->getCell('A7')->getValue())->toBe('Training 19')
        ->and($book->getSheetByName('C5_L&D cont.')->getCell('A8')->getValue())->toBeEmpty();
});

/**
 * Whether the form's checkbox for this cell is ticked, control and all.
 */
function boxIsTicked(Spreadsheet $book, string $sheetName, string $cell): bool
{
    $sheet = $book->getSheetByName($sheetName);
    $controls = $book->getUnparsedLoadedData()['sheets'][$sheet->getCodeName()]['ctrlProps'] ?? [];

    foreach ($controls as $control) {
        if (! str_contains($control['content'], 'fmlaLink="'.$cell.'"')) {
            continue;
        }

        return str_contains($control['content'], 'checked="Checked"')
            && $sheet->getCell($cell)->getValue() === true;
    }

    return false;
}

test('sex, civil status and citizenship tick their boxes rather than print words', function () {
    $employee = pdsEmployee(['gender' => 'Female']);

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'civil_status' => 'Married',
        'citizenship' => 'Filipino',
    ]);

    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());

    expect(boxIsTicked($book, 'C1', 'E16'))->toBeTrue()
        ->and(boxIsTicked($book, 'C1', 'D16'))->toBeFalse()
        ->and(boxIsTicked($book, 'C1', 'E17'))->toBeTrue()
        ->and(boxIsTicked($book, 'C1', 'D17'))->toBeFalse()
        ->and(boxIsTicked($book, 'C1', 'J13'))->toBeTrue()
        ->and(boxIsTicked($book, 'C1', 'K13'))->toBeFalse();
});

test('a dual citizenship names its basis and its country', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'citizenship' => 'Dual Citizenship',
        'dual_citizenship_basis' => 'by naturalization',
        'dual_citizenship_country' => 'Canada',
    ]);

    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());
    $sheet = $book->getSheetByName('C1');

    // The country dropdown stores the line it landed on, not the name.
    $chosen = $sheet->getCell('Q'.(10 + (int) $sheet->getCell('J16')->getValue()))->getValue();

    expect(boxIsTicked($book, 'C1', 'K13'))->toBeTrue()
        ->and(boxIsTicked($book, 'C1', 'M14'))->toBeTrue()
        ->and(trim((string) $chosen))->toBe('Canada');
});

test('an employee answers the questions on page 4', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.related_within_third_degree', '0')
        ->set('form.convicted_of_crime', '0')
        ->set('form.solo_parent', '1')
        ->set('form.solo_parent_id_no', 'SP-2024-0091')
        ->set('form.government_id_type', 'PRC')
        ->set('form.government_id_number', '0123456')
        ->call('savePageFour')
        ->assertHasNoErrors();

    $sheet = $employee->fresh()->personalDataSheet;

    expect($sheet->related_within_third_degree)->toBeFalse()
        ->and($sheet->convicted_of_crime)->toBeFalse()
        ->and($sheet->solo_parent)->toBeTrue()
        ->and($sheet->solo_parent_id_no)->toBe('SP-2024-0091')
        ->and($sheet->government_id_type)->toBe('PRC');
});

test('a yes with no details behind it is refused', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.criminally_charged', '1')
        ->call('savePageFour')
        ->assertHasErrors('form.criminally_charged_detail');
});

test('a no needs no details', function () {
    pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('form.criminally_charged', '0')
        ->call('savePageFour')
        ->assertHasNoErrors();
});

test('a no survives the round trip rather than reading as unanswered', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'convicted_of_crime' => false,
    ]);

    Livewire::test('pages::my-pds')->assertSet('form.convicted_of_crime', '0');
});

test('page 4 ticks the yes and the no boxes', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'related_within_third_degree' => false,
        'criminally_charged' => true,
        'criminally_charged_detail' => 'Dismissed for lack of merit',
        'criminally_charged_date_filed' => '2019-02-11',
        'criminally_charged_status' => 'Dismissed',
        'person_with_disability' => null,
    ]);

    $book = app(FillPersonalDataSheet::class)->handle($employee->fresh());
    $sheet = $book->getSheetByName('C4');

    expect(boxIsTicked($book, 'C4', 'J3'))->toBeTrue()
        ->and(boxIsTicked($book, 'C4', 'H3'))->toBeFalse()
        ->and(boxIsTicked($book, 'C4', 'H18'))->toBeTrue()
        ->and(boxIsTicked($book, 'C4', 'J18'))->toBeFalse()
        // Unanswered leaves both boxes alone.
        ->and(boxIsTicked($book, 'C4', 'H45'))->toBeFalse()
        ->and(boxIsTicked($book, 'C4', 'J45'))->toBeFalse()
        ->and($sheet->getCell('L19')->getValue())->toBe('Dismissed for lack of merit')
        ->and($sheet->getCell('L20')->getValue())->toBe('11/02/2019')
        ->and($sheet->getCell('L21')->getValue())->toBe('Dismissed');
});

test('an employee lists their references', function () {
    $employee = pdsEmployee();

    Livewire::test('pages::my-pds')
        ->set('references.0.full_name', 'Dr. Jose P. Rizal')
        ->set('references.0.address', 'Calamba, Laguna')
        ->set('references.0.contact', '09171234567')
        ->call('savePageFour')
        ->assertHasNoErrors();

    expect($employee->fresh()->references->first()->full_name)->toBe('Dr. Jose P. Rizal');
});

test('references land on the last three lines of page 4', function () {
    $employee = pdsEmployee();

    EmployeeReference::factory()->for($employee)->create([
        'full_name' => 'Dr. Jose P. Rizal',
        'address' => 'Calamba, Laguna',
        'contact' => '09171234567',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C4');

    expect($sheet->getCell('A52')->getValue())->toBe('Dr. Jose P. Rizal')
        ->and($sheet->getCell('G52')->getValue())->toBe('Calamba, Laguna')
        ->and($sheet->getCell('H52')->getValue())->toEqual('09171234567')
        ->and($sheet->getCell('A53')->getValue())->toBeEmpty();
});

test('the government ID lands under the declaration', function () {
    $employee = pdsEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'government_id_type' => 'PRC',
        'government_id_number' => '0123456',
        'government_id_issued' => '20/01/2024, Butuan City',
    ]);

    $sheet = app(FillPersonalDataSheet::class)->handle($employee->fresh())->getSheetByName('C4');

    expect($sheet->getCell('D61')->getValue())->toBe('PRC')
        ->and($sheet->getCell('D62')->getValue())->toEqual('0123456')
        ->and($sheet->getCell('D64')->getValue())->toBe('20/01/2024, Butuan City');
});
