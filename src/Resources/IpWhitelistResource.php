<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/** Whitelist IP requise pour les payouts -- partagée par toutes les clés du marchand (cf. apps.api_keys.models.MerchantIpWhitelistEntry côté API). Fonctionne bien via clé API -- vérifié en conditions réelles. */
final class IpWhitelistResource
{
    public function __construct(private readonly Http $http)
    {
    }

    /**
     * @param array{page?: int, page_size?: int} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-ip-whitelist/', $params);
    }

    /** @param array{ip_address: string, label?: string} $params */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/merchant-ip-whitelist/', [], $params);
    }

    /** @param array{ip_address?: string, label?: string, status?: string} $params */
    public function update(string $id, array $params): array
    {
        return $this->http->request('PATCH', "/merchant-ip-whitelist/{$id}/", [], $params);
    }

    public function delete(string $id): void
    {
        $this->http->request('DELETE', "/merchant-ip-whitelist/{$id}/");
    }
}
