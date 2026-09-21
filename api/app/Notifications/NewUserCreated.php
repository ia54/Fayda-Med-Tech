<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserCreated extends Notification
{
    use Queueable;

    protected $password;
    protected $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct($password, $userName)
    {
        $this->password = $password;
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Welcome to Faydamed - Your Account Has Been Created')
                    ->greeting('Hello ' . $this->userName . '!')
                    ->line('Your account has been successfully created by an administrator.')
                    ->line('Here are your login credentials:')
                    ->line('**Email:** ' . $notifiable->email)
                    ->line('**Temporary Password:** ' . $this->password)
                    ->line('**Role:** ' . ucfirst(str_replace('_', ' ', $notifiable->role)))
                    ->action('Login to Your Account', url('/'))
                    ->line('Please change your password after your first login for security purposes.')
                    ->line('If you have any questions, please contact the administrator.')
                    ->line('Thank you for using Faydamed!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $notifiable->id,
            'email' => $notifiable->email,
            'role' => $notifiable->role,
        ];
    }
}
