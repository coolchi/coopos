<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'approval_workflow_id', 'approvable_type', 'approvable_id',
        'current_step', 'status', 'requested_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }
}
