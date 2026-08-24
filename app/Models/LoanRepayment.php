<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'loan_id', 'installment_number', 'due_on', 'principal_due_kobo',
        'interest_due_kobo', 'fees_due_kobo', 'penalty_due_kobo', 'amount_due_kobo',
        'amount_paid_kobo', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function outstandingKobo(): int
    {
        return max(0, $this->amount_due_kobo - $this->amount_paid_kobo);
    }
}
