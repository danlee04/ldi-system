<?php

namespace App\Actions\Training;

use App\Models\TrainingRecord;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class DeleteTrainingRecord
{
    /**
     * Withdraw a record nobody has acted on yet — submitted twice, or by
     * mistake. TrainingRecordPolicy::delete() is what keeps a decided one
     * out of here; this does not check again.
     *
     * The notice waiting in the approver's bell goes with it, or they
     * would open their queue to find nothing there.
     */
    public function handle(TrainingRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            DatabaseNotification::query()
                ->where('data->training_record_id', $record->getKey())
                ->delete();

            $record->delete();
        });
    }
}
