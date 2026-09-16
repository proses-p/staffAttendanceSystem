<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <title>Attendance Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .header p {
            margin: 5px 0;
            color: #666;
        }

        .period {
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Attendance Report</h1>
        <p>Dudumizi Staff Attendance System</p>
    </div>

    <div class="period">
        <strong>Report Period:</strong>
        {{ $from }} to {{ $to }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Staff Name</th>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Duration</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ $loop->iteration }}</td>

                    <td>
                        {{ $attendance->user->name ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $attendance->attendance_date }}
                    </td>

                    <td>
                        {{ $attendance->check_in_time ?? '-' }}
                    </td>

                    <td>
                        {{ $attendance->check_out_time ?? '-' }}
                    </td>

                    <td>
                        {{ $attendance->work_duration ?? '-' }}
                    </td>

                    <td>
                        {{ ucfirst($attendance->status ?? 'N/A') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">
                        No attendance records found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>