# CoopOS

Multi-tenant SaaS for Nigerian cooperative societies. Conventional, Islamic (Qard Hasan, Murabaha), and hybrid workspaces.

## Stack

Laravel 13, PHP 8.3, Inertia.js, Vue 3, Tailwind CSS 4, SQLite locally (MySQL 8+ in production).

## Local setup

```bash
cd ~/Sites/coopos
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
```

Herd: `herd link coopos` then open [http://coopos.test](http://coopos.test).

## Demo logins

| Role | Email | Password |
|---|---|---|
| Super Admin | nathan.k@example.net | Password1 |
| Ijebu Staff (conventional) | ursula.b@example.com | Password1 |
| Al-Barakah (Islamic) | xena.w@example.org | Password1 |
| Hybrid | xavier.y@example.org | Password1 |

Member portal users are seeded as `member{n}.{tenant-slug}@coopos.test` / `Password1`.

## Architecture notes

- Path tenancy: `/{tenant}/dashboard` and `/{tenant}/portal`
- Shared MySQL/SQLite with `tenant_id` global scopes (fail closed)
- Money stored as integer kobo
- Islamic products never use `InterestCalculator`
- Payment drivers: `manual`, `paystack`, plus Flutterwave/Monnify stubs
