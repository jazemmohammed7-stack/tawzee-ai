<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetLink extends Mailable
{
    public function __construct(public string $resetUrl) {}

    public function build(): static
    {
        return $this->subject(__('authentication.mail_subject'))->view('mail.password-reset');
    }
}
