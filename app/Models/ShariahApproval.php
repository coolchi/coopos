<?php

namespace App\Models;

use App\Enums\ShariahStatus;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShariahApproval extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'islamic_financing_product_id', 'adviser_name', 'status',
        'approved_on', 'notes', 'document_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShariahStatus::class,
            'approved_on' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(IslamicFinancingProduct::class, 'islamic_financing_product_id');
    }
}
