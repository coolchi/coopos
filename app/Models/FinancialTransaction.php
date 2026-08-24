<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialTransaction extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'reference', 'member_id', 'type', 'amount_kobo', 'status',
        'channel', 'idempotency_key', 'journal_entry_id', 'related_type',
        'related_id', 'created_by', 'initiated_at', 'completed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
