<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerPasswordCode extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fish Happy password reset')
            ->line('Enter this reset code in the Fish Happy app.')
            ->line($this->token)
            ->line('This code expires in 60 minutes.');
    }
}
