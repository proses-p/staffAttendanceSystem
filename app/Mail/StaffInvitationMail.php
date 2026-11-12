<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Mail\Mailable;

class StaffInvitationMail extends Mailable
{
    private const REGISTRATION_URL_BASE = 'https://app.taarifa.wadudu.co.tz';

    public function __construct(public StaffInvitation $invitation)
    {
    }

    public function build()
    {
        return $this
            ->subject('You are invited to the Staff Attendance System')
            ->view('emails.staff-invitation')
            ->with([
                'registrationUrl' => self::REGISTRATION_URL_BASE.route(
                    'staff.register',
                    [
                        'token' => $this->invitation->token,
                        'email' => $this->invitation->email,
                    ],
                    false
                ),
            ]);
    }
}
