<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Sessions de checkout à usage unique -- chacune génère sa propre page de
 * paiement hébergée (checkout_url), pré-remplie pour un client précis.
 * Contrairement à un lien de paiement réutilisable, une session correspond
 * à UNE tentative de paiement.
 */
final class CheckoutSessionsResource
{
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * @param array{page?: int, page_size?: int, search?: string, status?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/checkout-sessions/', $params);
    }

    /**
     * @param array{amount: int|float|string, currency: string, description?: string, country?: string, customer_email: string, customer_name: string, customer_phone?: string, return_url?: string, metadata?: array<string, mixed>} $params
     * @param string|bool|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function create(array $params, $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/checkout-sessions/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/checkout-sessions/{$id}/");
    }

    /** @return array<string, mixed> */
    public function cancel(string $id): array
    {
        return $this->http->request('POST', "/checkout-sessions/{$id}/cancel/");
    }
}
