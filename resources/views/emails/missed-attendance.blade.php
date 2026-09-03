<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Notification</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial,sans-serif;">

    <div style="max-width:600px; margin:40px auto; background:#ffffff; border-radius:10px; overflow:hidden;">

        <div style="padding:25px; text-align:center; background-color:#ed6632; color:white;">
            <h2 style="margin:0;">Staff Attendance System</h2>
        </div>

        <div style="padding:35px 30px;">

            <h2>Hello {{ $staffName }},</h2>

            <p style="font-size:16px; line-height:1.6;">
                You have passed the expected time to be at the office.
            </p>

            <p style="font-size:16px; line-height:1.6;">
                Please sign in as soon as possible if you are at the office.
            </p>

            <div style="text-align:center; margin:30px 0;">

                <a href="{{ route('staff.dashboard', [], false) }}"
                   style="
                        display:inline-block;
                        padding:14px 28px;
                        background-color:#ed6632;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:6px;
                        font-weight:bold;
                   ">
                    SIGN IN NOW
                </a>

            </div>

            {{-- <p style="font-size:14px; color:#666;">
                Your location will be verified before attendance is recorded.
            </p> --}}

            <p style="font-size:14px; color:#666;">
                Staff Attendance System
            </p>

        </div>

    </div>

</body>
</html>
