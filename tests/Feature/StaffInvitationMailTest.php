<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use Tests\TestCase;

class StaffInvitationMailTest extends TestCase
{
    public function test_invitation_email_uses_the_verified_mobile_app_link(): void
    {
        $token = str_repeat('a', 64);
        $email = 'staff@example.test';
        $invitation = new StaffInvitation([
            'token' => $token,
            'email' => $email,
        ]);

        $mail = (new StaffInvitationMail($invitation))->build();
        $uri = parse_url($mail->viewData['registrationUrl']);

        $this->assertSame('https', $uri['scheme']);
        $this->assertSame('taarifa.wadudu.co.tz', $uri['host']);
        $this->assertSame("/staff/register/{$token}", $uri['path']);
        parse_str($uri['query'], $query);
        $this->assertSame($email, $query['email']);
    }
}
