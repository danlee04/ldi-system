<?php

use App\Actions\Users\SaveUserAccount;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;

/**
 * The fields the form would have validated and handed to the action.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountAttributes(array $overrides = []): array
{
    return [
        'employeeId' => null,
        'name' => 'Juana dela Cruz',
        'email' => 'juana@example.test',
        'role' => UserRole::Employee->value,
        'password' => 'correct-horse-battery',
        'isActive' => true,
        'keepsCalendar' => false,
        ...$overrides,
    ];
}

test('an account the admin makes in person needs no verification mail', function () {
    $account = app(SaveUserAccount::class)->handle(null, accountAttributes());

    expect($account->email_verified_at)->not->toBeNull();
});

test('taking the employee off an account releases them', function () {
    $account = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $account->getKey()]);

    app(SaveUserAccount::class)->handle($account, accountAttributes([
        'email' => $account->email,
        'password' => '',
        'employeeId' => null,
    ]));

    expect($employee->fresh()->user_id)->toBeNull();
});
