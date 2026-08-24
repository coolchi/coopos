<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'sort_order', 'description', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function typeLabel(): string
    {
        return $this->type instanceof AccountType ? ucfirst($this->type->value) : (string) $this->type;
    }

    public function normalCreditBalance(): bool
    {
        $type = $this->type instanceof AccountType ? $this->type->value : (string) $this->type;

        return in_array($type, ['liability', 'equity', 'income'], true);
    }
}
