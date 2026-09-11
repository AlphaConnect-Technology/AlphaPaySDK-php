<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Reversements (retraits vers un compte bancaire ou mobile money enregistré)
 * -- cf. WalletTransfersResource pour un transfert entre wallets AlphaPay.
 *
 * ATTENTION : entièrement inaccessible via clé API (sk_live_.../sk_test_...),
 * y compris en LECTURE -- toutes les méthodes de cette ressource lèvent
 * systématiquement une AlphaPayPermissionException (403, code
 * "dashboard_only"). Restriction volontaire côté API
 * (apps.core.mixins.forbid_api_key, vérifiée contre le code réel ET contre
 * une vraie clé live) : un retrait ne peut être déclenché ou consulté que
 * par un compte utilisateur connecté au dashboard, jamais par une clé API --
 * même la vôtre. Cette ressource ne peut donc pas servir à une intégration
 * serveur automatisée ; elle reste dans le SDK pour rester honnête sur la
 * forme des endpoints, pas pour un usage réel avec ce client.
 */
final class SettlementsResource
{
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * @param array{page?: int, page_size?: int, search?: string, status?: string, country?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/settlements/', $params);
    }

    /**
     * Débite le solde disponible du pays concerné dès la création --
     * recommandé avec $idempotencyKey.
     *
     * @param array{country: string, requested_amount: int|float|string, payout_method: string} $params
     * @param string|bool|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function create(array $params, $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/settlements/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/settlements/{$id}/");
    }

    /** Uniquement possible tant que le reversement est PENDING. */
    public function cancel(string $id): array
    {
        return $this->http->request('POST', "/settlements/{$id}/cancel/");
    }
}
