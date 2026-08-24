<?php

namespace App\Models;

use App\Enums\VoucherType;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingVoucher extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'type', 'reference', 'account_id', 'contra_account_id',
        'amount_kobo', 'occurred_on', 'payee', 'description', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'occurred_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
