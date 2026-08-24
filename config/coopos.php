<?php

return [
    'name' => env('APP_NAME', 'CoopOS'),
    'currency' => 'NGN',
    'tenancy' => [
        'subdomains' => env('TENANCY_SUBDOMAINS', true),
        'central_domain' => env('CENTRAL_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    ],
    'reserved_slugs' => [
        'login', 'register', 'logout', 'dashboard', 'password', 'platform', 'api', 'up',
        'join', 'invite', 'webhooks', 'horizon', 'telescope', 'livewire',
        'sanctum', 'email', 'verify', 'storage', 'vendor', 'build',
        'www', 'app', 'admin', 'mail', 'cdn', 'static',
    ],
    'trial_days' => (int) env('TRIAL_DAYS', 14),
    'default_member_prefix' => 'M',
    'default_transaction_access_code' => env('TRANSACTION_ACCESS_CODE', '1234'),
    'guarantor_exposure_cap_kobo' => 5_000_000_00,
    'themes' => [
        'teal' => ['label' => 'Teal', 'ink' => '#071c1b', 'gold' => '#d4b06a', 'brand' => '#0f766e'],
        'crimson' => ['label' => 'Crimson', 'ink' => '#1c0709', 'gold' => '#e0b56a', 'brand' => '#d7050c'],
        'navy' => ['label' => 'Navy', 'ink' => '#0b1220', 'gold' => '#c9a46c', 'brand' => '#1d4e89'],
        'forest' => ['label' => 'Forest', 'ink' => '#0c1a12', 'gold' => '#c4a35a', 'brand' => '#166534'],
        'indigo' => ['label' => 'Indigo', 'ink' => '#12101f', 'gold' => '#d4b06a', 'brand' => '#4f46e5'],
        'amber' => ['label' => 'Amber', 'ink' => '#1a1206', 'gold' => '#e8c36a', 'brand' => '#b45309'],
        'rose' => ['label' => 'Rose', 'ink' => '#1a0b12', 'gold' => '#e0b56a', 'brand' => '#9f1239'],
    ],
    'nigeria_states' => [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue',
        'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu',
        'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi',
        'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun',
        'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
    ],
];
