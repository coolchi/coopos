<?php

namespace App\Domain\Membership;

use App\Domain\Branches\BranchService;
use App\Domain\Notifications\Notifier;
use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Models\Member;
use App\Models\MemberGuarantor;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?User $actor = null): Member
    {
        $tenant = TenantContext::require();

        $member = DB::transaction(function () use ($data, $tenant) {
            $settings = $tenant->settings;
            $branchId = app(BranchService::class)->defaultBranchId(
                $data['branch_id'] ?? BranchContext::id()
            );
            $status = ($settings?->require_membership_approval ?? true)
                ? MemberStatus::Pending
                : MemberStatus::Active;

            $user = null;
            if (! empty($data['email']) && ! empty($data['password'])) {
                $user = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'type' => UserType::Member,
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                ]);
                setPermissionsTeamId($tenant->id);
                $user->assignRole('Member');
            }

            $member = Member::query()->create([
                'branch_id' => $branchId,
                'user_id' => $user?->id,
                'membership_number' => $settings?->nextMembershipNumber() ?? 'M'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $status,
                'category' => $data['category'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'employer' => $data['employer'] ?? null,
                'employment_id' => $data['employment_id'] ?? null,
                'joined_on' => $status === MemberStatus::Active ? now()->toDateString() : null,
                'invite_token' => Str::random(40),
                'sponsor_member_id' => $data['sponsor_member_id'] ?? null,
            ]);

            $member->profile()->create([
                'tenant_id' => $tenant->id,
                'residential_address' => $data['address'] ?? null,
                'state' => $data['state'] ?? null,
                'lga' => $data['lga'] ?? null,
                'bvn_encrypted' => $data['bvn'] ?? null,
                'nin_encrypted' => $data['nin'] ?? null,
            ]);

            if (! empty($data['next_of_kin_name'])) {
                $member->nextOfKin()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['next_of_kin_name'],
                    'relationship' => $data['next_of_kin_relationship'] ?? 'Relative',
                    'phone' => $data['next_of_kin_phone'] ?? null,
                    'address' => $data['next_of_kin_address'] ?? null,
                ]);
            }

            if (! empty($data['guarantor_member_id'])) {
                $this->attachGuarantor($member, (int) $data['guarantor_member_id']);
            }

            return $member->load('profile', 'nextOfKin', 'memberGuarantors.guarantor');
        });

        app(Notifier::class)->memberRegistered($member);

        return $member;
    }

    public function approve(Member $member): Member
    {
        $member->update([
            'status' => MemberStatus::Active,
            'joined_on' => $member->joined_on ?? now()->toDateString(),
        ]);

        app(Notifier::class)->memberApproved($member);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data) {
            if (isset($data['branch_id']) && ! BranchContext::isConstrained()) {
                $member->branch_id = app(BranchService::class)->defaultBranchId((int) $data['branch_id']);
            }

            $member->update([
                'branch_id' => $member->branch_id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? $member->middle_name,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? $member->gender,
                'date_of_birth' => $data['date_of_birth'] ?? $member->date_of_birth,
                'phone' => $data['phone'] ?? $member->phone,
                'email' => $data['email'] ?? $member->email,
                'occupation' => $data['occupation'] ?? $member->occupation,
                'employer' => $data['employer'] ?? $member->employer,
                'sponsor_member_id' => array_key_exists('sponsor_member_id', $data)
                    ? $data['sponsor_member_id']
                    : $member->sponsor_member_id,
            ]);

            $member->profile()->updateOrCreate(
                ['member_id' => $member->id],
                [
                    'tenant_id' => $member->tenant_id,
                    'residential_address' => $data['address'] ?? $member->profile?->residential_address,
                    'state' => $data['state'] ?? $member->profile?->state,
                    'lga' => $data['lga'] ?? $member->profile?->lga,
                    'bvn_encrypted' => $data['bvn'] ?? $member->profile?->bvn_encrypted,
                    'nin_encrypted' => $data['nin'] ?? $member->profile?->nin_encrypted,
                ],
            );

            if (! empty($data['next_of_kin_name'])) {
                $kin = $member->nextOfKin()->first();
                $payload = [
                    'tenant_id' => $member->tenant_id,
                    'name' => $data['next_of_kin_name'],
                    'relationship' => $data['next_of_kin_relationship'] ?? 'Relative',
                    'phone' => $data['next_of_kin_phone'] ?? null,
                    'address' => $data['next_of_kin_address'] ?? null,
                ];
                if ($kin) {
                    $kin->update($payload);
                } else {
                    $member->nextOfKin()->create($payload);
                }
            }

            if ($member->user && ($data['email'] ?? null)) {
                $member->user->update([
                    'name' => $member->fullName(),
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? $member->user->phone,
                ]);
            }

            return $member->fresh()->load('profile', 'nextOfKin', 'memberGuarantors.guarantor');
        });
    }

    public function addGuarantor(Member $member, Member $guarantor): MemberGuarantor
    {
        return $this->attachGuarantor($member, $guarantor->id);
    }

    public function removeGuarantor(Member $member, MemberGuarantor $record): void
    {
        if ($record->member_id !== $member->id) {
            abort(404);
        }

        $record->delete();
    }

    private function attachGuarantor(Member $member, int $guarantorMemberId): MemberGuarantor
    {
        if ($guarantorMemberId === $member->id) {
            throw ValidationException::withMessages([
                'guarantor_uuid' => 'A member cannot guarantee themselves.',
            ]);
        }

        $guarantor = Member::query()->find($guarantorMemberId);

        if (! $guarantor) {
            throw ValidationException::withMessages([
                'guarantor_uuid' => 'Choose an existing member as guarantor.',
            ]);
        }

        $exists = MemberGuarantor::query()
            ->where('member_id', $member->id)
            ->where('guarantor_member_id', $guarantorMemberId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'guarantor_uuid' => 'This member is already a guarantor.',
            ]);
        }

        return $member->memberGuarantors()->create([
            'tenant_id' => $member->tenant_id,
            'guarantor_member_id' => $guarantorMemberId,
        ]);
    }

    /**
     * @param  iterable<array<string, string>>  $rows
     * @return array{imported: int, failed: int, errors: array<int, string>}
     */
    public function importCsv(iterable $rows): array
    {
        return Notifier::mute(function () use ($rows) {
            $imported = 0;
            $failed = 0;
            $errors = [];
            $line = 1;

            foreach ($rows as $row) {
                $line++;
                $first = trim((string) ($row['first_name'] ?? ''));
                $last = trim((string) ($row['last_name'] ?? ''));
                if ($first === '' && $last === '') {
                    continue;
                }
                try {
                    if ($first === '' || $last === '') {
                        throw new \InvalidArgumentException('first_name and last_name are required.');
                    }
                    $row['first_name'] = $first;
                    $row['last_name'] = $last;
                    $row['branch_id'] = app(BranchService::class)->findByUuidOrCode($row['branch'] ?? $row['branch_code'] ?? null)?->id;
                    $introducer = $row['introducer'] ?? $row['sponsor'] ?? $row['introducer_membership_number'] ?? null;
                    if ($introducer) {
                        $row['sponsor_member_id'] = Member::query()
                            ->where('membership_number', $introducer)
                            ->value('id');
                    }
                    $this->register($row);
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$line}: ".$e->getMessage();
                }
            }

            return compact('imported', 'failed', 'errors');
        });
    }
}
