<?php

namespace App\Actions\Ldna;

use App\Models\Competency;
use App\Models\Employee;
use App\Models\LdnaCycle;
use App\Models\User;
use App\Notifications\LdnaCycleOpened;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OpenLdnaCycle
{
    public function __construct(private readonly EnrolInLdnaCycle $enrol) {}

    /**
     * Sets up a year's assessment for everybody still working here, and
     * tells each of them when they can fill it in.
     *
     * The year being unique and the window running forwards are the
     * form's to check. This checks the one thing the form cannot see.
     *
     * @throws ValidationException when there is nothing to assess people on
     */
    public function handle(User $by, int $year, CarbonImmutable $opensOn, CarbonImmutable $closesOn): LdnaCycle
    {
        abort_unless($by->isAdminOrHr(), 403);

        if (! Competency::query()->active()->exists()) {
            throw ValidationException::withMessages([
                'year' => __('Add at least one active competency in Setup before opening a cycle.'),
            ]);
        }

        $cycle = DB::transaction(function () use ($by, $year, $opensOn, $closesOn): LdnaCycle {
            $cycle = LdnaCycle::create([
                'year' => $year,
                'opens_on' => $opensOn,
                'closes_on' => $closesOn,
                'created_by' => $by->getKey(),
            ]);

            Employee::query()->active()->each(function (Employee $employee) use ($cycle): void {
                $this->enrol->handle($cycle, $employee);
            });

            return $cycle;
        });

        Notification::send(
            User::query()
                ->where('is_active', true)
                ->whereIn('id', Employee::query()->active()->whereNotNull('user_id')->select('user_id'))
                ->get(),
            new LdnaCycleOpened($cycle),
        );

        return $cycle;
    }
}
