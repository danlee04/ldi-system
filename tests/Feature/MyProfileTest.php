<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEligibility;
use App\Models\PersonalDataSheet;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Signs in an employee and hands back their record.
 */
function profileEmployee(array $attributes = []): Employee
{
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create([...$attributes, 'user_id' => $user->id]);

    test()->actingAs($user);

    return $employee;
}

test('an employee sees their own details', function () {
    $section = Section::factory()->create(['name' => 'Human Resource Development Section']);

    profileEmployee([
        'first_name' => 'Maria',
        'last_name' => 'Cruz',
        'employee_number' => 'EMP-0042',
        'section_id' => $section->id,
    ]);

    $this->get(route('my-profile'))
        ->assertOk()
        ->assertSee('Maria')
        ->assertSee('EMP-0042')
        ->assertSee('Human Resource Development Section');
});

test('an account with no employee record cannot open it', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('my-profile'))->assertForbidden();
});

test('nobody else profile is reachable from here', function () {
    profileEmployee(['last_name' => 'Mine']);

    Employee::factory()->create(['last_name' => 'Somebody Else']);

    Livewire::test('pages::my-profile')->assertDontSee('Somebody Else');
});

test('the cpd units count only approved training from this year', function () {
    $employee = profileEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 12,
        'date_end' => now()->startOfYear()->addMonth(),
    ]);

    // Approved, but last year.
    TrainingRecord::factory()->for($employee)->approved()->create([
        'cpd_units' => 8,
        'date_end' => now()->subYear(),
    ]);

    // This year, but nobody has decided on it.
    TrainingRecord::factory()->for($employee)->create([
        'cpd_units' => 5,
        'date_end' => now()->startOfYear()->addMonths(2),
    ]);

    expect(Livewire::test('pages::my-profile')->instance()->cpdUnits)->toBe(12.0);
});

test('their eligibility is listed', function () {
    $employee = profileEmployee();

    EmployeeEligibility::factory()->for($employee)->create([
        'detail' => 'CSP - Career Service Professional',
        'rating' => '86.45',
    ]);

    Livewire::test('pages::my-profile')
        ->assertSee('CSP - Career Service Professional')
        ->assertSee('86.45');
});

test('an empty pds reports every section as still to do', function () {
    $employee = profileEmployee();

    $progress = app(PersonalDataSheetProgress::class);
    $sections = $progress->handle($employee);

    expect($sections)->toHaveCount(8)
        ->and($progress->percentage($sections))->toBe(0)
        ->and(collect($sections)->every(fn (array $section): bool => ! $section['filled']))->toBeTrue();
});

test('a section counts as done once it has anything in it', function () {
    $employee = profileEmployee();

    PersonalDataSheet::factory()->create([
        'employee_id' => $employee->id,
        'father_last_name' => 'Santos',
        'convicted_of_crime' => false,
    ]);

    EmployeeEducation::factory()->create(['employee_id' => $employee->id]);

    $sections = collect(app(PersonalDataSheetProgress::class)->handle($employee->fresh()))
        ->keyBy('number');

    expect($sections['I']['filled'])->toBeTrue()
        ->and($sections['II']['filled'])->toBeTrue()
        ->and($sections['III']['filled'])->toBeTrue()
        ->and($sections['IV']['filled'])->toBeFalse()
        // A no is an answer, so page 4 has been started.
        ->and($sections['34-41']['filled'])->toBeTrue();
});

test('the page names the sections still empty', function () {
    profileEmployee();

    Livewire::test('pages::my-profile')
        ->assertSee('Civil service eligibility')
        ->assertSee('Work experience')
        ->assertSee('0%');
});

test('learning and development is not something they are asked to fill', function () {
    $employee = profileEmployee();

    TrainingRecord::factory()->for($employee)->approved()->create();

    $numbers = collect(app(PersonalDataSheetProgress::class)->handle($employee))->pluck('number');

    expect($numbers)->not->toContain('VI')
        ->and(app(PersonalDataSheetProgress::class)->learningAndDevelopment($employee))->toBe(1);
});

test('an administrative account is not offered a profile in the sidebar', function () {
    $this->actingAs(User::factory()->hr()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('my-profile'))
        ->assertDontSee(route('my-pds'));
});

test('an employee is offered one', function () {
    profileEmployee();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('my-profile'));
});

test('an employee can put a photograph on their profile', function () {
    Storage::fake('public');

    $employee = profileEmployee();

    Livewire::test('pages::my-profile')
        ->call('choosePhoto')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto')
        ->assertHasNoErrors()
        // The sidebar listens for this, which is how the face appears
        // there without a page load.
        ->assertDispatched('photo-updated');

    $employee->refresh();

    expect($employee->photo_path)->not->toBeNull()
        // The stored name is generated, never the one it arrived with.
        ->and($employee->photo_path)->not->toContain('me.jpg')
        ->and(Storage::disk('public')->exists($employee->photo_path))->toBeTrue();
});

test('replacing a photograph does not leave the old one on the disk', function () {
    Storage::fake('public');

    $employee = profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('first.jpg', 400, 400))
        ->call('savePhoto');

    $first = $employee->refresh()->photo_path;

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('second.jpg', 400, 400))
        ->call('savePhoto');

    $second = $employee->refresh()->photo_path;

    expect($second)->not->toBe($first)
        ->and(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists($second))->toBeTrue();
});

test('removing the photograph puts the initials back', function () {
    Storage::fake('public');

    $employee = profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto');

    $path = $employee->refresh()->photo_path;

    Livewire::test('pages::my-profile')
        ->call('removePhoto')
        ->assertDispatched('photo-updated');

    expect($employee->refresh()->photo_path)->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and($employee->photoUrl())->toBeNull();
});

test('a file that is not a picture is refused', function () {
    Storage::fake('public');

    profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->create('payroll.pdf', 100, 'application/pdf'))
        ->call('savePhoto')
        ->assertHasErrors('photo');
});

test('a picture over two megabytes is refused', function () {
    Storage::fake('public');

    profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('huge.jpg', 400, 400)->size(3000))
        ->call('savePhoto')
        ->assertHasErrors('photo');
});

test('a picture too small to recognise anybody by is refused', function () {
    Storage::fake('public');

    profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('tiny.jpg', 80, 80))
        ->call('savePhoto')
        ->assertHasErrors('photo');
});

test('the sidebar shows the photograph once there is one, and initials before', function () {
    Storage::fake('public');

    $employee = profileEmployee();
    // The initials in the sidebar come off the account, which is what a
    // sign-in is named by.
    $initials = auth()->user()->initials();

    Livewire::test('profile-avatar')->assertSee($initials);

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto');

    Livewire::test('profile-avatar')
        ->assertSee($employee->refresh()->photo_path)
        ->assertDontSee($initials);
});

test('a page draws the photograph on both profile buttons', function () {
    Storage::fake('public');

    $employee = profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto');

    $path = $employee->refresh()->photo_path;

    // The layout carries two of them — the sidebar's, which is what a
    // desktop shows, and the mobile header's. Both, plus the two avatars
    // inside their menus, make four.
    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, $path))->toBe(4);
});

test('the sidebar profile button carries the photograph', function () {
    Storage::fake('public');

    $employee = profileEmployee();

    Livewire::test('pages::my-profile')
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto');

    // The desktop shape, which is the one that stayed on initials.
    Livewire::test('profile-avatar', ['name' => 'Maria Cruz', 'sidebar' => true])
        ->assertSee($employee->refresh()->photo_path)
        ->assertSee('Maria Cruz');
});

test('the sidebar names a person the way they would write it', function () {
    $employee = profileEmployee([
        'first_name' => 'Lloyd',
        'middle_name' => 'Bislig',
        'last_name' => 'Abao',
        'suffix' => null,
    ]);

    expect($employee->personal_name)->toBe('Lloyd B. Abao')
        // The roster keeps its own order; the two are different questions.
        ->and($employee->listing_name)->toBe('Abao, Lloyd B.');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Lloyd B. Abao');
});

test('an account with no employee record still has a name in the corner', function () {
    $this->actingAs(User::factory()->hr()->create(['name' => 'HR Office']));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('HR Office');
});

test('the profile button is a direct child of its dropdown, so the name truncates', function () {
    profileEmployee(['first_name' => 'Mary Jane', 'middle_name' => 'Espina', 'last_name' => 'Lao Guico']);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    // Flux widens the button with `[ui-dropdown>&]:w-full`, which stops
    // matching the moment anything wraps it — and then the button grows to
    // the length of the name and pushes the sidebar off the screen.
    $withoutComments = preg_replace('/<!--.*?-->/s', '', $html);

    expect($withoutComments)->toMatch('/<ui-dropdown[^>]*>\s*<button/')
        ->and($html)->toContain('Mary Jane E. Lao Guico');
});
