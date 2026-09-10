<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Transferts entre les wallets multi-pays d'un même marchand (avec
 * conversion automatique si les devises diffèrent).
 *
 * ATTENTION : entièrement inaccessible via clé API -- même restriction que
 * SettlementsResource, vérifiée en conditions réelles (403, code
 * "dashboard_only") : uniquement pilotable depuis le dashboard (compte
 * utilisateur), jamais une clé API.
 */
final class WalletTransfersResource
{
    public function __construct(private readonly Http $http)
    {
    }

    /**
     * @param array{page?: int, page_size?: int, search?: string, status?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/wallet-transfers/', $params);
    }

    /**
     * @param array{from_country: string, to_country: string, from_amount: int|float|string} $params
     * @return array<string, mixed>
     */
    public function create(array $params, string|bool|null $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/wallet-transfers/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/wallet-transfers/{$id}/");
    }
}
