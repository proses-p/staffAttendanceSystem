<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfDevelopmentController extends Controller
{
    public function download(Request $request) {
        // 1. filter the specific data from database
        // 2. combining the data into one place
        // 3. summary metric
        $from = $request->input('from');
        $to = $request->input('to');

        $attendances = Attendance::with('user')
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get();

        $pdf = Pdf::loadView('admin.reports.attendance', ['attendances' => $attendances,
            'from' => $from,
            'to' => $to,
        ]);

        return $pdf->download('attendance-report.pdf');
    }
}
