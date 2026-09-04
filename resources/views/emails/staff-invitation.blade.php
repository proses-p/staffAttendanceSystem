<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Staff invitation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 10px; overflow: hidden;">
        <div style="padding: 25px; text-align: center; background-color: #ed6632; color: white;">
            <h2 style="margin: 0;">Staff Attendance System</h2>
        </div>

        <div style="padding: 35px 30px;">
            <h2>You have been invited to join the staff attendance system.</h2>
            <p style="font-size: 16px; line-height: 1.6;">
                Complete your staff registration using the button below. This invitation expires in 24 hours.
            </p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $registrationUrl }}"
                   style="display: inline-block; padding: 14px 28px; background-color: #ed6632; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">
                    Complete Registration
                </a>
            </div>

            <p style="font-size: 14px; color: #666;">
                If the button does not work, open this link in your browser:<br>
                <a href="{{ $registrationUrl }}">{{ $registrationUrl }}</a>
            </p>
        </div>
    </div>
</body>
</html>
