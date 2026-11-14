<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetCodeMail extends Mailable
{
    public function __construct(public string $code)
    {
    }

    public function build()
    {
        return $this
            ->subject('Your Staff Attendance password reset code')
            ->view('emails.password-reset-code');
    }
}
