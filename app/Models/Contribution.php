<?php

namespace App\Models;

use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'contribution_product_id', 'contribution_schedule_id',
        'amount_kobo', 'paid_on', 'for_month', 'channel', 'financial_transaction_id', 'recorded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'for_month' => 'date',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id');
    }
}
