<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanGuarantor extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'loan_application_id', 'guarantor_member_id', 'exposure_kobo',
        'status', 'responded_at', 'response_note',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }
}
