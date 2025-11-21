<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends Notification
{
    // use Queueable;

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build verification URL (signed URL)
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $verifyUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Email Akun - Foodlab')
            ->greeting('Hai ' . $notifiable->name . '!')
            ->line('Terima kasih telah mendaftar di Foodlab.')
            ->line('Sebelum melanjutkan, silakan verifikasi alamat email Anda terlebih dahulu.')
            ->action('Verifikasi Email', $verifyUrl)
            ->line('Link ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak membuat akun ini, abaikan email ini.')
            ->salutation('Salam hangat, Tim Foodlab');
    }
}
