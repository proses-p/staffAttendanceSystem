<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Mail\Mailable;

class StaffInvitationMail extends Mailable
{
    private const ADMIN_ONBOARDING_URL_BASE = 'https://wadudu.co.tz';

    private const STAFF_REGISTRATION_URL_BASE = 'https://app.taarifa.wadudu.co.tz';

    public function __construct(public StaffInvitation $invitation)
    {
    }

    public function build()
    {
        if ($this->invitation->role === 'admin') {
            $registrationUrl = self::ADMIN_ONBOARDING_URL_BASE.route(
                'company.onboarding',
                ['token' => $this->invitation->token],
                false
            );
        } else {
            $registrationUrl = self::STAFF_REGISTRATION_URL_BASE.route(
                'staff.register',
                [
                    'token' => $this->invitation->token,
                    'email' => $this->invitation->email,
                ],
                false
            );
        }

        return $this
            ->subject('You are invited to the Staff Attendance System')
            ->view('emails.staff-invitation')
            ->with([
                'registrationUrl' => $registrationUrl,
            ]);
    }
}
