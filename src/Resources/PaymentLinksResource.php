<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

final class PaymentLinksResource
{
    public function __construct(private readonly Http $http)
    {
    }

    /**
     * @param array{page?: int, page_size?: int, search?: string, ordering?: string, is_active?: bool} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/payment-links/', $params);
    }

    /**
     * @param array{name: string, description?: string, amount_type?: string, amount?: int|float|string|null, min_amount?: int|float|string|null, currency: string, expires_at?: ?string, usage_limit?: ?int} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/payment-links/', [], $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/payment-links/{$id}/");
    }

    /** @param array<string, mixed> $params */
    public function update(string $id, array $params): array
    {
        return $this->http->request('PATCH', "/payment-links/{$id}/", [], $params);
    }

    public function delete(string $id): void
    {
        $this->http->request('DELETE', "/payment-links/{$id}/");
    }
}
