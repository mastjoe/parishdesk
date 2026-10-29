<?php

namespace App\Notifications;

use App\Models\ParishSetting;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification implements ShouldQueue
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
            ->subject(__('You have been invited to :parish', ['parish' => $parish]))
            ->greeting(__('Hello :name,', ['name' => $this->invitation->name]))
            ->line(__('You have been invited to join the :parish team on ParishDesk.', ['parish' => $parish]))
            ->action(__('Accept invitation'), $this->invitation->acceptUrl())
            ->line(__('This link expires on :date.', ['date' => $this->invitation->expires_at->toFormattedDayDateString()]));
    }
}
