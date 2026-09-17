<?php

namespace App\Actions\Users;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaveUserAccount
{
    /**
     * Create or amend a sign-in account, and say whose it is.
     *
     * Two things the form does not spell out. A blank password on an
     * amendment leaves the current one alone, so an admin fixing a
     * spelling does not lock somebody out; and a new account is marked
     * verified on the spot, because the admin made it in front of the
     * person rather than mailing them a link.
     *
     * @param  array<string, mixed>  $attributes  employeeId, name, email, role, password, isActive, keepsCalendar
     */
    public function handle(?User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $values = [
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'role' => $attributes['role'],
                'is_active' => $attributes['isActive'],
                'can_manage_calendar' => $attributes['keepsCalendar'],
            ];

            if ($attributes['password'] !== '') {
                $values['password'] = $attributes['password'];
            }

            $saved = User::updateOrCreate(['id' => $user?->getKey()], $values);

            // Stamped after the fact, not passed in above: email_verified_at
            // is deliberately left out of the model's fillable list, so a
            // mass assignment drops it without a word.
            if ($user === null) {
                $saved->forceFill(['email_verified_at' => now()])->save();
            }

            $this->linkEmployee($saved, $attributes['employeeId']);

            return $saved;
        });
    }

    /**
     * An employee holds at most one account, so moving an account to a
     * different person must release the previous one — otherwise two
     * records point at the same sign-in and the roster shows the account
     * twice.
     */
    private function linkEmployee(User $user, ?int $employeeId): void
    {
        Employee::query()
            ->where('user_id', $user->getKey())
            ->when($employeeId !== null, fn (Builder $query) => $query->whereKeyNot($employeeId))
            ->update(['user_id' => null]);

        if ($employeeId === null) {
            return;
        }

        Employee::query()->whereKey($employeeId)->update(['user_id' => $user->getKey()]);
    }
}
