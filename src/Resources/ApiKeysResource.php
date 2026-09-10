<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Gestion des clés API du marchand, et whitelist IP (via $ipWhitelist).
 *
 * ATTENTION : list()/create()/get()/revoke()/delete() ci-dessous sont
 * entièrement inaccessibles via clé API (403, code "dashboard_only", vérifié
 * en conditions réelles) -- cohérent avec la sécurité attendue : une clé
 * compromise ne doit pas pouvoir créer d'autres clés pour elle-même ni
 * lister les clés existantes. Seul $ipWhitelist fonctionne via clé API.
 */
final class ApiKeysResource
{
    public readonly IpWhitelistResource $ipWhitelist;

    public function __construct(private readonly Http $http)
    {
        $this->ipWhitelist = new IpWhitelistResource($http);
    }

    /**
     * @param array{page?: int, page_size?: int} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-api-keys/', $params);
    }

    /**
     * `secret` n'est présent QUE dans cette réponse -- jamais récupérable
     * ensuite, à stocker immédiatement côté appelant.
     *
     * @param array{name?: string, environment: string, scope?: string, expires_at?: ?string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/merchant-api-keys/', [], $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/merchant-api-keys/{$id}/");
    }

    public function revoke(string $id): array
    {
        return $this->http->request('POST', "/merchant-api-keys/{$id}/revoke/");
    }

    public function delete(string $id): void
    {
        $this->http->request('DELETE', "/merchant-api-keys/{$id}/");
    }
}
