<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Points de terminaison webhook du marchand (CRUD), abonnements par type
 * d'événement ($subscriptions), et historique des livraisons ($logs). Pour
 * vérifier la signature d'un webhook reçu, voir \AlphaPay\Webhook::verifySignature()
 * (classe à part, pas une méthode de cette ressource).
 *
 * Vérifié en conditions réelles : list()/get() (webhooks, abonnements,
 * journal) fonctionnent bien via clé API -- mais toute écriture est
 * dashboard-only (403, code "dashboard_only") : create(), update(),
 * rotateSecret(), delete(), subscriptions->subscribe()/unsubscribe(),
 * logs->resend(). Repérable individuellement ci-dessous.
 */
final class WebhookEndpointsResource
{
    public WebhookSubscriptionsResource $subscriptions;
    public WebhookLogsResource $logs;
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
        $this->subscriptions = new WebhookSubscriptionsResource($http);
        $this->logs = new WebhookLogsResource($http);
    }

    /**
     * @param array{page?: int, page_size?: int} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-webhooks/', $params);
    }

    /**
     * ATTENTION : dashboard-only -- 403 via clé API.
     * `signing_secret` n'est présent en clair dans la réponse qu'à la création (ou après rotateSecret()) -- jamais récupérable ensuite.
     *
     * @param array{url: string, description?: string, environment: string, signing_secret?: string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/merchant-webhooks/', [], $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/merchant-webhooks/{$id}/");
    }

    /**
     * ATTENTION : dashboard-only -- 403 via clé API.
     *
     * @param array{url?: string, description?: string, is_active?: bool} $params
     */
    public function update(string $id, array $params): array
    {
        return $this->http->request('PATCH', "/merchant-webhooks/{$id}/", [], $params);
    }

    /**
     * ATTENTION : dashboard-only -- 403 via clé API.
     * Génère et renvoie un nouveau secret -- l'ancien cesse immédiatement de
     * valider les signatures. Le secret est généré ICI, côté SDK, et envoyé
     * explicitement : un PATCH signing_secret vide ne régénère RIEN côté API
     * une fois qu'un secret existe déjà (il ne s'auto-génère qu'à la
     * création initiale) -- même constat que dans le dashboard AlphaPay
     * (Webhooks.tsx.generateSigningSecret).
     */
    public function rotateSecret(string $id): array
    {
        $signingSecret = bin2hex(random_bytes(32));
        return $this->http->request('PATCH', "/merchant-webhooks/{$id}/", [], ['signing_secret' => $signingSecret]);
    }

    /** ATTENTION : dashboard-only -- 403 via clé API. */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', "/merchant-webhooks/{$id}/");
    }
}
