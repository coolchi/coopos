<?php

namespace App\Jobs;

use App\Domain\Savings\ContributionService;
use App\Models\ContributionSchedule;
use App\Models\Tenant;
use App\Notifications\ContributionReminderNotification;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessContributionReminders implements ShouldQueue
{
    use Queueable;

    public function handle(ContributionService $contributions): void
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($contributions) {
            TenantContext::set($tenant);
            $contributions->applyPenalties();

            ContributionSchedule::query()
                ->where('status', 'pending')
                ->whereDate('due_on', now()->addDays(3)->toDateString())
                ->whereNull('reminded_at')
                ->with('member.user')
                ->each(function (ContributionSchedule $schedule) {
                    if ($schedule->member?->user) {
                        $schedule->member->user->notify(new ContributionReminderNotification($schedule));
                    }
                    $schedule->update(['reminded_at' => now()]);
                });
        });

        TenantContext::forget();
    }
}
