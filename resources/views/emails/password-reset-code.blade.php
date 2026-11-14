<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password reset code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 40px auto; padding: 32px; background: #ffffff; border-radius: 10px;">
        <h1 style="margin-top: 0; color: #172033;">Reset your password</h1>
        <p style="font-size: 16px; line-height: 1.6; color: #475569;">
            Enter this code in the Staff Attendance app to choose a new password:
        </p>
        <p style="margin: 28px 0; color: #c2410c; font-size: 32px; font-weight: bold; letter-spacing: 8px;">
            {{ $code }}
        </p>
        <p style="font-size: 14px; line-height: 1.6; color: #64748b;">
            This code expires in 15 minutes. If you did not request a password reset, you can ignore this email.
        </p>
    </div>
</body>
</html>
