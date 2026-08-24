<?php

namespace App\Support;

use App\Models\Tenant;

class TenantTheme
{
    public const DEFAULT = 'teal';

    /**
     * @return array<string, array{label: string, ink: string, gold: string, brand: string}>
     */
    public static function catalog(): array
    {
        return config('coopos.themes', []);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * @return list<array{key: string, label: string, ink: string, gold: string, brand: string}>
     */
    public static function options(): array
    {
        return collect(self::catalog())
            ->map(fn (array $theme, string $key) => [
                'key' => $key,
                'label' => $theme['label'],
                'ink' => $theme['ink'],
                'gold' => $theme['gold'],
                'brand' => $theme['brand'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, ink: string, gold: string, brand: string}
     */
    public static function resolve(?Tenant $tenant): array
    {
        $catalog = self::catalog();
        $key = $tenant?->theme;

        if (! $key || ! isset($catalog[$key])) {
            $key = self::matchColor($tenant?->brand_color) ?? self::DEFAULT;
        }

        $theme = $catalog[$key] ?? $catalog[self::DEFAULT];

        return [
            'key' => $key,
            'label' => $theme['label'],
            'ink' => $theme['ink'],
            'gold' => $theme['gold'],
            'brand' => $theme['brand'],
        ];
    }

    public static function matchColor(?string $hex): ?string
    {
        $needle = strtoupper(ltrim((string) $hex, '#'));
        if ($needle === '') {
            return null;
        }

        foreach (self::catalog() as $key => $theme) {
            if (strtoupper(ltrim($theme['brand'], '#')) === $needle) {
                return $key;
            }
        }

        return null;
    }
}
