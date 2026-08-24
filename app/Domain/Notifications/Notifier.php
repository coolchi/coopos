<?php

namespace App\Domain\Notifications;

use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Tenancy;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class Notifier
{
    private static int $muteDepth = 0;

    public static function mute(callable $callback): mixed
    {
        self::$muteDepth++;

        try {
            return $callback();
        } finally {
            self::$muteDepth--;
        }
    }

    public function send(iterable $users, string $title, string $body, ?string $url = null, string $category = 'general', bool $includeActor = false): void
    {
        if (self::$muteDepth > 0) {
            return;
        }

        $actorId = auth()->id();
        $recipients = Collection::make($users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->when(
                ! $includeActor && $actorId,
                fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $actorId),
            )
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AppNotification($title, $body, $url, $category));
    }

    public function toUser(?User $user, string $title, string $body, ?string $url = null, string $category = 'general', bool $includeActor = false): void
    {
        if (! $user) {
            return;
        }

        $this->send([$user], $title, $body, $url, $category, $includeActor);
    }

    public function toMember(?Member $member, string $title, string $body, ?string $url = null, string $category = 'general'): void
    {
        $member?->loadMissing('user');
        $this->toUser($member?->user, $title, $body, $url ?? $this->memberHome($member), $category, includeActor: true);
    }

    public function toStaff(string $title, string $body, ?string $url = null, string $category = 'general', ?string $permission = null, ?Tenant $tenant = null, bool $includeActor = false): void
    {
        $this->send($this->staff($permission, $tenant), $title, $body, $url, $category, $includeActor);
    }

    public function memberRegistered(Member $member): void
    {
        $name = $member->fullName();

        if ($member->status === MemberStatus::Pending) {
            $this->toStaff(
                'Membership pending',
                "{$name} needs approval before they can transact.",
                $this->staffPath('members/'.$member->uuid),
                'membership',
                'members.approve',
            );

            return;
        }

        $this->toMember(
            $member,
            'Welcome to the cooperative',
            'Your membership is active. You can now contribute and apply for financing.',
            $this->memberPath($member, 'portal'),
            'membership',
        );
    }

    public function memberApproved(Member $member): void
    {
        $this->toMember(
            $member,
            'Membership approved',
            'Your membership is active. You can now contribute and apply for financing.',
            $this->memberPath($member, 'portal'),
            'membership',
        );
    }

    public function contributionRecorded(Member $member, int $amountKobo, string $productName): void
    {
        $this->toMember(
            $member,
            'Contribution recorded',
            money($amountKobo).' was recorded for '.$productName.'.',
            $this->memberPath($member, 'portal/statement'),
            'contribution',
        );
    }

    public function withdrawalRequested(Member $member, int $amountKobo): void
    {
        $this->toStaff(
            'Withdrawal requested',
            $member->fullName().' requested '.money($amountKobo).'.',
            $this->staffPath('withdrawals'),
            'withdrawal',
            'withdrawals.approve',
        );
        $this->toMember(
            $member,
            'Withdrawal submitted',
            'Your request for '.money($amountKobo).' is awaiting approval.',
            $this->memberPath($member, 'portal/statement'),
            'withdrawal',
        );
    }

    public function withdrawalPaid(Member $member, int $amountKobo): void
    {
        $this->toMember(
            $member,
            'Withdrawal paid',
            money($amountKobo).' has been paid out to your bank account.',
            $this->memberPath($member, 'portal/statement'),
            'withdrawal',
        );
    }

    public function approvalRequested(string $label, ?Member $member, int $amountKobo, string $url): void
    {
        $who = $member?->fullName() ?: 'A member';
        $this->toStaff(
            'Approval needed',
            "{$who} submitted {$label} for ".money($amountKobo).'.',
            $url,
            'approval',
            'approvals.act',
            includeActor: true,
        );
    }

    public function approvalRejected(?Member $member, string $label, ?string $reason = null): void
    {
        $body = "Your {$label} was declined.";
        if (filled($reason)) {
            $body .= ' '.$reason;
        }

        $this->toMember($member, ucfirst($label).' declined', $body, $this->memberPath($member, 'portal'), 'approval');
    }

    public function financingApproved(?Member $member, string $label): void
    {
        $this->toMember(
            $member,
            ucfirst($label).' approved',
            "Your {$label} was approved and will be disbursed shortly.",
            $this->memberPath($member, 'portal'),
            'loan',
        );
    }

    public function financingDisbursed(?Member $member, string $label, int $amountKobo): void
    {
        $this->toMember(
            $member,
            ucfirst($label).' disbursed',
            money($amountKobo).' has been disbursed for your '.$label.'.',
            $this->memberPath($member, 'portal/statement'),
            'loan',
        );
    }

    public function guaranteeRequested(Member $guarantor, Member $applicant, int $amountKobo): void
    {
        $this->toMember(
            $guarantor,
            'Guarantee requested',
            $applicant->fullName().' asked you to guarantee '.money($amountKobo).'.',
            $this->memberPath($guarantor, 'portal'),
            'loan',
        );
    }

    public function announcementPublished(Announcement $announcement): void
    {
        if ($announcement->published_at === null) {
            return;
        }

        $title = $announcement->is_platform ? 'Platform announcement' : 'New announcement';
        $body = trim($announcement->title);
        $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($announcement->body)) ?: ''), 140);
        if ($excerpt !== '' && $excerpt !== $body) {
            $body .= ' — '.$excerpt;
        }

        if ($announcement->is_platform) {
            $this->send(
                User::query()->where('is_platform_admin', true)->get(),
                $title,
                $body,
                '/platform/announcements',
                'announcement',
                includeActor: true,
            );

            Tenant::query()->each(function (Tenant $tenant) use ($title, $body) {
                $this->send(
                    $this->staff(null, $tenant),
                    $title,
                    $body,
                    $this->path($tenant, 'communication'),
                    'announcement',
                    includeActor: true,
                );
                $this->send(
                    $this->memberUsers($tenant),
                    $title,
                    $body,
                    $this->path($tenant, 'portal/announcements'),
                    'announcement',
                    includeActor: true,
                );
            });

            return;
        }

        $tenant = $announcement->tenant ?? TenantContext::current();
        $this->toStaff($title, $body, $this->path($tenant, 'communication'), 'announcement', null, $tenant, includeActor: true);
        $this->send(
            $this->announcementMembers($announcement),
            $title,
            $body,
            $this->path($tenant, 'portal/announcements'),
            'announcement',
            includeActor: true,
        );
    }

    public function supportTicketOpened(SupportTicket $ticket): void
    {
        $this->send(
            $this->platformAdmins(),
            'New support ticket',
            $ticket->subject,
            '/platform/tickets/'.$ticket->uuid,
            'support',
        );
    }

    public function supportTicketReplied(SupportTicket $ticket, User $actor): void
    {
        $ticket->loadMissing(['user', 'tenant']);

        if ($actor->isPlatformAdmin()) {
            $this->toUser(
                $ticket->user,
                'Support reply',
                'The CoopOS team replied to “'.$ticket->subject.'”.',
                $this->path($ticket->tenant, 'support/'.$ticket->uuid),
                'support',
            );

            return;
        }

        $this->send(
            $this->platformAdmins(),
            'Ticket reply',
            $ticket->subject,
            '/platform/tickets/'.$ticket->uuid,
            'support',
        );
    }

    public function cooperativeStatus(Tenant $tenant, string $title, string $body): void
    {
        $this->send(
            $this->staff(null, $tenant),
            $title,
            $body,
            $this->path($tenant, 'dashboard'),
            'announcement',
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(?string $permission = null, ?Tenant $tenant = null): Collection
    {
        $tenantId = $tenant?->id ?? TenantContext::id();
        if (! $tenantId) {
            return collect();
        }

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('type', UserType::Staff)
            ->get();

        if (! $permission) {
            return $users;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            return $users->filter(fn (User $user) => $user->can($permission))->values();
        } finally {
            setPermissionsTeamId($previous);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function memberUsers(?Tenant $tenant = null, ?MemberStatus $status = null, ?int $branchId = null): Collection
    {
        $tenantId = $tenant?->id ?? TenantContext::id();
        if (! $tenantId) {
            return collect();
        }

        return Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('user_id')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('user')
            ->get()
            ->map(fn (Member $member) => $member->user)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function announcementMembers(Announcement $announcement): Collection
    {
        $tenant = $announcement->tenant ?? Tenant::query()->find($announcement->tenant_id);

        return match ($announcement->audience ?: 'all') {
            'active' => $this->memberUsers($tenant, MemberStatus::Active),
            'branch' => $this->memberUsers($tenant, null, $announcement->branch_id),
            default => $this->memberUsers($tenant),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function platformAdmins(): Collection
    {
        return User::query()->where('is_platform_admin', true)->get();
    }

    private function staffPath(string $suffix): string
    {
        return $this->path(TenantContext::current(), $suffix);
    }

    private function memberPath(?Member $member, string $suffix): ?string
    {
        if (! $member) {
            return null;
        }

        $tenant = $member->tenant ?? TenantContext::current() ?? Tenant::query()->find($member->tenant_id);

        return $this->path($tenant, $suffix);
    }

    private function memberHome(?Member $member): ?string
    {
        return $this->memberPath($member, 'portal');
    }

    private function path(?Tenant $tenant, string $suffix): string
    {
        $prefix = $tenant ? Tenancy::pathPrefix($tenant) : Tenancy::pathPrefix();

        return $prefix.'/'.ltrim($suffix, '/');
    }
}
