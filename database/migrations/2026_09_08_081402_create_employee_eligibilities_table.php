<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section IV of CS Form No. 212, and the single home for eligibility.
     *
     * The employee record used to carry one eligibility inline. The form
     * allows several, so the columns move here and the old ones go: two
     * places holding the same fact would drift apart.
     */
    public function up(): void
    {
        Schema::create('employee_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eligibility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('detail')->nullable();
            $table->string('rating', 40)->nullable();
            $table->date('date_of_examination')->nullable();
            $table->string('place_of_examination')->nullable();
            $table->string('license_number', 60)->nullable();
            $table->date('date_of_validity')->nullable()->index();
            $table->timestamps();

            $table->index('employee_id');
        });

        DB::table('employees')
            ->select('id', 'eligibility_id', 'eligibility_detail', 'eligibility_expires_on')
            ->where(function ($query): void {
                $query->whereNotNull('eligibility_id')
                    ->orWhereNotNull('eligibility_detail')
                    ->orWhereNotNull('eligibility_expires_on');
            })
            ->orderBy('id')
            ->chunk(200, function ($employees): void {
                DB::table('employee_eligibilities')->insert(
                    $employees->map(fn ($employee): array => [
                        'employee_id' => $employee->id,
                        'eligibility_id' => $employee->eligibility_id,
                        'detail' => $employee->eligibility_detail,
                        'date_of_validity' => $employee->eligibility_expires_on,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all(),
                );
            });

        // SQLite refuses to drop an indexed column while the index stands.
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['eligibility_expires_on']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_id');
            $table->dropColumn(['eligibility_detail', 'eligibility_expires_on']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('eligibility_id')->nullable()->after('position_id')->constrained()->nullOnDelete();
            $table->string('eligibility_detail')->nullable()->after('eligibility_id');
            $table->date('eligibility_expires_on')->nullable()->after('eligibility_detail')->index();
        });

        Schema::dropIfExists('employee_eligibilities');
    }
};
