<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Staff reminder</title>
</head>

<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: Arial, sans-serif;">

    <div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 10px; overflow: hidden;">

        <div style="padding: 25px; text-align: center; background-color: #ed6632; color: white;">
            <h2 style="margin: 0;">Staff Attendance System</h2>
        </div>

        <div style="padding: 35px 30px;">

            <h2>Hello {{ $staffName }},</h2>

            <p style="font-size: 16px; line-height: 1.6;">
                It's time to be at the office. Please once you reach near the office location, sign in for today's attendance confirmation before
                <strong>8:00 AM</strong>.
            </p>

            <div style="text-align: center; margin: 30px 0;">

                <a href="{{ route('staff.dashboard', [], false) }}"
                   style="
                        display: inline-block;
                        padding: 14px 28px;
                        background-color: #ed6632;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: bold;
                   ">
                    SIGN IN NOW
                </a>

            </div>

            <p style="font-size: 14px; color: #666;">
                click the sign in button and you will be redirected to the attendance confirmation page.
            </p>

            <p style="font-size: 14px; color: #666;">
                Staff Attendance System
            </p>

        </div>

    </div>

</body>
</html>
