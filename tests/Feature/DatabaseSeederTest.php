<?php

use App\Enums\UserRole;
use App\Models\User;

test('seeding creates one sign-in per role', function () {
    $this->seed();

    expect(User::count())->toBe(5)
        ->and(User::query()->pluck('role')->all())
        ->toEqualCanonicalizing(UserRole::cases());
});

test('seeding twice does not duplicate accounts', function () {
    $this->seed();
    $this->seed();

    expect(User::count())->toBe(5);
});

test('seeding repairs an account that predates the role column', function () {
    User::factory()->create(['email' => 'test@example.com', 'role' => UserRole::Employee]);

    $this->seed();

    expect(User::query()->firstWhere('email', 'test@example.com')->role)->toBe(UserRole::Admin)
        ->and(User::count())->toBe(5);
});
