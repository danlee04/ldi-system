<?php

use App\Actions\Ldna\OpenLdnaCycle;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Opens LDNA for the coming year, today inside its window, with one core
 * competency asked of everybody at Intermediate.
 */
function openLdna(): LdnaCycle
{
    Competency::factory()->core(ProficiencyLevel::Intermediate)->withIndicators()->create(['name' => 'Delivering service excellence']);

    return app(OpenLdnaCycle::class)->handle(
        User::factory()->hr()->create(),
        now()->year + 1,
        today()->subDay(),
        today()->addMonth(),
    );
}

function assessmentOf(Employee $employee, LdnaCycle $cycle): LdnaAssessment
{
    return $cycle->assessments()->where('employee_id', $employee->id)->firstOrFail();
}
