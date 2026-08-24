<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MurabahaAsset extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'murabaha_financing_id', 'name', 'description', 'ownership_status',
    ];

    public function financing(): BelongsTo
    {
        return $this->belongsTo(MurabahaFinancing::class, 'murabaha_financing_id');
    }
}
