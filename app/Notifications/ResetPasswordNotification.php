<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = rtrim((string) config('app.frontend_url'), '/') . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset Password Akun Presensi Event')
            ->greeting('Halo!')
            ->line('Kami menerima permintaan reset password untuk akun Anda.')
            ->action('Reset Password', $resetUrl)
            ->line('Link reset password ini berlaku selama ' . config('auth.passwords.users.expire') . ' menit.')
            ->line('Jika Anda tidak meminta reset password, abaikan email ini.');
    }
}
