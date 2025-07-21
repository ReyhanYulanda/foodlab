<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{

    // use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }


    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Akun - Foodlab')
            ->greeting('Hai ' . $notifiable->name . '!')
            ->line('Kami menerima permintaan untuk mereset katasandi akun Foodlab Anda.')
            ->action('Reset Password', url('/reset-password/' . $this->token . '?email=' . urlencode($notifiable->email)))
            ->line('Link ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak meminta reset katasandi, abaikan email ini.')
            ->salutation('Salam hangat, Tim Foodlab');
    }
  
    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
