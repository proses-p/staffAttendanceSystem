<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Mail\Mailable;

class StaffInvitationMail extends Mailable
{
    public function __construct(public StaffInvitation $invitation)
    {
    }

    public function build()
    {
        return $this
            ->subject('You are invited to the Staff Attendance System')
            ->view('emails.staff-invitation')
            ->with([
                'registrationUrl' => route('staff.register', [
                    'token' => $this->invitation->token,
                ]),
            ]);
    }
}
