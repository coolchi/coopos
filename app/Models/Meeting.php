<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'title', 'held_on', 'kind', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }
}
