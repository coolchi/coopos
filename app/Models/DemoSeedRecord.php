<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DemoSeedRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'feature', 'model_type', 'model_id',
    ];
}
