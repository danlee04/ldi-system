<?php

namespace App\Http\Controllers;

use App\Actions\Pds\FillPersonalDataSheet;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands back the agency's own CS Form No. 212 workbook, filled in.
 */
class PersonalDataSheetController extends Controller
{
    /**
     * The signed-in employee's own sheet.
     */
    public function mine(Request $request, FillPersonalDataSheet $filler): StreamedResponse
    {
        $employee = $request->user()->employee;

        abort_if($employee === null, 403, __('Your account is not linked to an employee record.'));

        return $this->stream($filler, $employee);
    }

    /**
     * Somebody else's, for a 201 file. Scoped by the employee policy, so a
     * section head can only reach their own section.
     */
    public function show(Employee $employee, FillPersonalDataSheet $filler): StreamedResponse
    {
        Gate::authorize('view', $employee);

        return $this->stream($filler, $employee);
    }

    private function stream(FillPersonalDataSheet $filler, Employee $employee): StreamedResponse
    {
        $book = $filler->handle($employee);

        $name = str($employee->listing_name)->slug()->value().'-pds';

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, $name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
