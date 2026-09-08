<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * One local sign-in per role, all with the password "password".
     *
     * Re-running repairs the role on an account that already exists, which
     * matters for any user row created before the role column landed.
     *
     * @var list<array{string, string, UserRole}>
     */
    private const ACCOUNTS = [
        ['Admin User', 'test@example.com', UserRole::Admin],
        ['HR Officer', 'hr@example.com', UserRole::Hr],
        ['Division Head', 'divhead@example.com', UserRole::DivisionHead],
        ['Section Head', 'sechead@example.com', UserRole::SectionHead],
        ['Plain Employee', 'employee@example.com', UserRole::Employee],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            $user = User::query()->firstWhere('email', $email);

            if ($user === null) {
                User::factory()->create([
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                ]);

                continue;
            }

            $user->update(['role' => $role]);
        }
    }
}
