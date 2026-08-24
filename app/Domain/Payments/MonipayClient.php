<?php

namespace App\Domain\Payments;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MonipayClient
{
    public function __construct(private readonly ?Tenant $tenant = null) {}

    public function configured(): bool
    {
        return (bool) $this->secret();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomerWallet(array $payload): array
    {
        return $this->request('post', '/customer-wallet', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerWallet(string $walletId): array
    {
        return $this->request('get', '/customer-wallet/'.$walletId);
    }

    /**
     * @return list<array{id: string, name: string, settlement_bank_id: ?string, settlement_bank: ?array<string, mixed>}>
     */
    public function listRevenueHeads(): array
    {
        $payload = $this->request('get', '/payment/revenue-head', ['perPage' => 100]);
        $rows = $this->rows($payload);

        return collect($rows)->map(function ($row) {
            $bank = is_array($row['settlement_bank'] ?? null) ? $row['settlement_bank'] : null;

            return [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['rev_name'] ?? $row['name'] ?? $row['id'] ?? ''),
                'settlement_bank_id' => $row['settlement_bank_id'] ?? $bank['id'] ?? null,
                'settlement_bank' => $bank,
            ];
        })->filter(fn ($row) => $row['id'] !== '')->values()->all();
    }

    /**
     * @return list<array{id: string, name: string, account_no: ?string, account_name: ?string, bank_code: ?string, bank_name: ?string, label: string}>
     */
    public function listSettlementBanks(): array
    {
        $payload = $this->request('get', '/payment/settlement-bank', ['list' => 1]);
        $rows = $this->rows($payload);

        return collect($rows)->map(fn ($row) => $this->mapSettlementBank($row))
            ->filter(fn ($row) => $row['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * Fetch revenue heads and settlement banks in parallel.
     *
     * @return array{revenue_heads: list<array<string, mixed>>, settlement_banks: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        $secret = $this->secret();
        if (! $secret) {
            throw new RuntimeException('Monipay is not configured. Add a secret key in Settings.');
        }

        $base = rtrim((string) config('services.monipay.base_url'), '/');

        $responses = Http::pool(fn ($pool) => [
            $pool->as('heads')->baseUrl($base)->withToken($secret)->acceptJson()->timeout(8)
                ->get('/payment/revenue-head', ['perPage' => 100]),
            $pool->as('banks')->baseUrl($base)->withToken($secret)->acceptJson()->timeout(8)
                ->get('/payment/settlement-bank', ['list' => 1]),
        ]);

        return [
            'revenue_heads' => $this->parseRevenueHeads($this->unwrapPool($responses['heads'] ?? null)),
            'settlement_banks' => $this->parseSettlementBanks($this->unwrapPool($responses['banks'] ?? null)),
        ];
    }

    /**
     * @param  array{revname: string, account_no: string, bank_code: string}  $payload
     * @return array{id: string, name: string, settlement_bank_id: ?string, raw: array<string, mixed>}
     */
    public function createRevenueHead(array $payload): array
    {
        $json = $this->rawRequest('post', '/payment/revenue-head/create', $payload);
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $bank = is_array($data['settlement_bank'] ?? null) ? $data['settlement_bank'] : [];
        $id = (string) ($json['revhead'] ?? $data['id'] ?? $data['rev_code'] ?? '');

        if ($id === '') {
            throw new RuntimeException($json['message'] ?? 'Monipay did not return a revenue head id.');
        }

        return [
            'id' => $id,
            'name' => (string) ($data['rev_name'] ?? $payload['revname']),
            'settlement_bank_id' => $bank['id'] ?? ($data['settlement_splits'][0]['settlement_bank_id'] ?? null),
            'raw' => $json,
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->secret();

        if (! $secret || ! $signature) {
            return false;
        }

        $signature = str_starts_with($signature, 'sha512=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    public function secret(): ?string
    {
        $tenantSecret = $this->tenantExtras()['secret'] ?? null;

        return $tenantSecret ?: config('services.monipay.secret');
    }

    public function publicKey(): ?string
    {
        $tenantPublic = $this->tenantExtras()['public'] ?? null;

        return $tenantPublic ?: config('services.monipay.public');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $json = $this->rawRequest($method, $path, $payload);

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function rawRequest(string $method, string $path, array $payload = []): array
    {
        $secret = $this->secret();

        if (! $secret) {
            throw new RuntimeException('Monipay is not configured. Add a secret key in Settings.');
        }

        $pending = Http::baseUrl(rtrim((string) config('services.monipay.base_url'), '/'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout(25);

        $response = $method === 'get'
            ? $pending->get($path, $payload)
            : $pending->post($path, $payload);

        $json = $response->json() ?? [];
        $ok = ($json['status'] ?? false) === true || ($json['success'] ?? false) === true;

        if (! $response->successful() || ! $ok) {
            throw new RuntimeException($json['message'] ?? ('Monipay error HTTP '.$response->status()));
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rows(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_is_list($payload['data']) ? $payload['data'] : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrapPool(mixed $response): array
    {
        if (! is_object($response) || ! method_exists($response, 'successful')) {
            throw new RuntimeException('Monipay catalog request failed.');
        }

        $json = $response->json() ?? [];
        $ok = ($json['status'] ?? false) === true || ($json['success'] ?? false) === true;

        if (! $response->successful() || ! $ok) {
            throw new RuntimeException($json['message'] ?? ('Monipay error HTTP '.$response->status()));
        }

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array{id: string, name: string, settlement_bank_id: ?string, settlement_bank: ?array<string, mixed>}>
     */
    private function parseRevenueHeads(array $payload): array
    {
        return collect($this->rows($payload))->map(function ($row) {
            $bank = is_array($row['settlement_bank'] ?? null) ? $row['settlement_bank'] : null;

            return [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['rev_name'] ?? $row['name'] ?? $row['id'] ?? ''),
                'settlement_bank_id' => $row['settlement_bank_id'] ?? $bank['id'] ?? null,
                'settlement_bank' => $bank,
            ];
        })->filter(fn ($row) => $row['id'] !== '')->values()->all();
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array{id: string, name: string, account_no: ?string, account_name: ?string, bank_code: ?string, bank_name: ?string, label: string}>
     */
    private function parseSettlementBanks(array $payload): array
    {
        return collect($this->rows($payload))
            ->map(fn ($row) => $this->mapSettlementBank($row))
            ->filter(fn ($row) => $row['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, name: string, account_no: ?string, account_name: ?string, bank_code: ?string, bank_name: ?string, label: string}
     */
    private function mapSettlementBank(array $row): array
    {
        $name = (string) ($row['account_name'] ?? $row['name'] ?? 'Settlement account');
        $bank = (string) ($row['bank_name'] ?? '');
        $account = (string) ($row['account_no'] ?? '');
        $detail = collect([$bank, $account])->filter()->implode(' - ');

        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => $name,
            'account_no' => $account ?: null,
            'account_name' => $row['account_name'] ?? $name,
            'bank_code' => $row['bank_code'] ?? null,
            'bank_name' => $bank ?: null,
            'label' => $detail !== '' ? "{$name} · {$detail}" : $name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantExtras(): array
    {
        $tenant = $this->tenant ?? tenant();
        $settings = $tenant?->settings ?? TenantSetting::query()->first();

        return is_array($settings?->extras['monipay'] ?? null)
            ? $settings->extras['monipay']
            : [];
    }
}
