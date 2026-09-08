<?php

namespace App\Http\Controllers;

use App\Actions\Reports\DohLdiReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The DOH form is a printed page in its own right — landscape A4, its own
 * type scale, no application chrome — so it is served as a plain view
 * rather than squeezed into the app layout.
 */
class DohLdiReportController extends Controller
{
    public function __invoke(Request $request, DohLdiReport $report): View
    {
        abort_unless($request->user()->isAdminOrHr(), 403);

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $month = isset($validated['month']) ? (int) $validated['month'] : null;

        $rows = $report->handle($year, $month);

        return view('reports.doh-ldi', [
            'rows' => $rows,
            'totals' => $report->summarise($rows),
            'year' => $year,
            'month' => $month,
        ]);
    }
}
