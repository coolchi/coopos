<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToBranch, BelongsToTenant, HasFactory, HasPublicUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id', 'membership_number', 'first_name', 'middle_name',
        'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'status',
        'category', 'group_name', 'occupation', 'employer',
        'employment_id', 'photo_path', 'joined_on', 'membership_fee_kobo',
        'sponsor_member_id', 'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'date_of_birth' => 'date',
            'joined_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function nextOfKin(): HasMany
    {
        return $this->hasMany(MemberNextOfKin::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(MemberBeneficiary::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MemberBankAccount::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ContributionEnrollment::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sponsor_member_id');
    }

    public function memberGuarantors(): HasMany
    {
        return $this->hasMany(MemberGuarantor::class);
    }

    public function guaranteedMembers(): HasMany
    {
        return $this->hasMany(MemberGuarantor::class, 'guarantor_member_id');
    }

    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(MemberVirtualAccount::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function qardHasan(): HasMany
    {
        return $this->hasMany(QardHasanFinancing::class);
    }

    public function murabaha(): HasMany
    {
        return $this->hasMany(MurabahaFinancing::class);
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    public function canTransact(): bool
    {
        return $this->status->canTransact();
    }
}
