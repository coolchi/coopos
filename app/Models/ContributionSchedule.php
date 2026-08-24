<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionSchedule extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'member_id', 'contribution_product_id', 'contribution_enrollment_id',
        'due_on', 'amount_kobo', 'paid_kobo', 'penalty_kobo', 'status', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'reminded_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ContributionProduct::class, 'contribution_product_id');
    }

    public function outstandingKobo(): int
    {
        return max(0, $this->amount_kobo + $this->penalty_kobo - $this->paid_kobo);
    }
}
