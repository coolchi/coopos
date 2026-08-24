<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MurabahaInstallment extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'murabaha_financing_id', 'installment_number', 'due_on',
        'amount_due_kobo', 'amount_paid_kobo', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function financing(): BelongsTo
    {
        return $this->belongsTo(MurabahaFinancing::class, 'murabaha_financing_id');
    }

    public function outstandingKobo(): int
    {
        return max(0, $this->amount_due_kobo - $this->amount_paid_kobo);
    }
}
