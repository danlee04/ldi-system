<?php

namespace App\Actions\Ldna;

use App\Models\Employee;
use App\Models\LdnaCycle;
use App\Models\User;
use App\Notifications\LdnaCycleOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SyncLdnaCycle
{
    public function __construct(private readonly EnrolInLdnaCycle $enrol) {}

    /**
     * Enrols everybody still working here who has no assessment in the
     * cycle — somebody hired, or brought back, since it was set up. The
     * assessments already there are not touched.
     *
     * This action takes no User to check, so the caller must already have
     * confirmed HR or admin before calling it.
     *
     * @return int how many were added
     *
     * @throws ValidationException once the cycle has closed
     */
    public function handle(LdnaCycle $cycle): int
    {
        $cycle->ensureNotClosed();

        $missing = Employee::query()
            ->active()
            ->whereNotIn('id', $cycle->assessments()->select('employee_id'))
            ->get();

        DB::transaction(function () use ($cycle, $missing): void {
            foreach ($missing as $employee) {
                $this->enrol->handle($cycle, $employee);
            }
        });

        Notification::send(
            User::query()->where('is_active', true)->whereIn('id', $missing->pluck('user_id')->filter())->get(),
            new LdnaCycleOpened($cycle),
        );

        return $missing->count();
    }
}
