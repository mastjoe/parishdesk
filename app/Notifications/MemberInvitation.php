<?php

namespace App\Notifications;

use App\Models\ParishSetting;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public UserInvitation $invitation,
    ) {}

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
        $parish = ParishSetting::current()->name;

        return (new MailMessage)
            ->subject(__('Your :parish member portal', ['parish' => $parish]))
            ->greeting(__('Hello :name,', ['name' => $this->invitation->name]))
            ->line(__(':parish has set up a member portal account for you.', ['parish' => $parish]))
            ->line(__('In the portal you can see your family and parish records, keep your details up to date, and follow parish news.'))
            ->action(__('Set up my account'), $this->invitation->acceptUrl())
            ->line(__('This link expires on :date.', ['date' => $this->invitation->expires_at->toFormattedDayDateString()]));
    }
}
