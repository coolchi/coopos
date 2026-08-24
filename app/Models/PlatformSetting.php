<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allKeyed();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('platform_settings');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('platform_settings');
        static::applyToConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public static function allKeyed(): array
    {
        return Cache::remember('platform_settings', 60, function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'platform_name' => 'CoopOS',
            'tagline' => 'The operating system for Nigerian cooperatives',
            'support_email' => 'samuel.w@example.com',
            'support_phone' => '+234 201 330 4400',
            'from_email' => 'nathan.k@example.net',
            'trial_days' => 14,
            'allow_registration' => true,
            'require_cooperative_approval' => false,
            'default_plan' => 'professional',
            'central_domain' => config('coopos.tenancy.central_domain'),
            'subdomains_enabled' => (bool) config('coopos.tenancy.subdomains'),
            'maintenance_mode' => false,
            'allow_impersonation' => true,
            'paystack_public_key' => '',
            'sms_enabled' => false,
            'islamic_finance_enabled' => true,
            'hybrid_mode_enabled' => true,
            'api_enabled' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        return array_merge(static::defaults(), static::allKeyed());
    }

    public static function applyToConfig(): void
    {
        $settings = static::current();

        config([
            'coopos.name' => $settings['platform_name'],
            'coopos.trial_days' => (int) $settings['trial_days'],
            'mail.from.address' => $settings['from_email'] ?: config('mail.from.address'),
            'mail.from.name' => $settings['platform_name'] ?: config('mail.from.name'),
        ]);
    }
}
