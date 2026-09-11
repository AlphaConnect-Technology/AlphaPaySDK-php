<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/** Soldes par pays/devise et grand livre des mouvements -- lecture seule (les mouvements naissent des autres ressources : transactions, reversements, transferts). */
final class BalancesResource
{
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * @param array{page?: int, page_size?: int} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-balances/', $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/merchant-balances/{$id}/");
    }

    /**
     * ATTENTION : inaccessible via clé API (contrairement à list()/get()
     * ci-dessus, qui fonctionnent bien avec une clé -- vérifié en conditions
     * réelles) : lève systématiquement AlphaPayPermissionException (403,
     * code "dashboard_only"). Le grand livre détaillé n'est consultable que
     * depuis le dashboard (compte utilisateur).
     *
     * @param array{page?: int, page_size?: int, country?: string, created_at__gte?: string, created_at__lte?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function ledgerEntries(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-ledger-entries/', $params);
    }
}
