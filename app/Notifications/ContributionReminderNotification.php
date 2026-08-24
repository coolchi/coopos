<?php

namespace App\Notifications;

use App\Models\ContributionSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContributionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContributionSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = money($this->schedule->amount_kobo);

        return (new MailMessage)
            ->subject('Contribution reminder')
            ->line("Your contribution of {$amount} is due on {$this->schedule->due_on->toFormattedDateString()}.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Contribution due',
            'body' => 'Your contribution of '.money($this->schedule->amount_kobo).' is due on '.$this->schedule->due_on->toDateString().'.',
            'url' => tenant_path('portal/statement'),
            'category' => 'contribution',
        ];
    }
}
