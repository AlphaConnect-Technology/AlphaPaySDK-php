<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

final class CustomersResource
{
    public function __construct(private readonly Http $http)
    {
    }

    /**
     * @param array{page?: int, page_size?: int, search?: string, ordering?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/customers/', $params);
    }

    /**
     * @param array{phone?: string, country: string, full_name?: string, email?: string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/customers/', [], $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/customers/{$id}/");
    }

    /** @param array<string, mixed> $params */
    public function update(string $id, array $params): array
    {
        return $this->http->request('PATCH', "/customers/{$id}/", [], $params);
    }

    public function delete(string $id): void
    {
        $this->http->request('DELETE', "/customers/{$id}/");
    }

    /**
     * Contrairement à TransactionsResource::list(), cet endpoint EST paginé
     * par page ("page" fonctionne ici) -- CustomerTransactionsView n'a pas
     * de pagination_class propre, il retombe donc sur le défaut global
     * (StandardResultsPagination), pas CreatedAtCursorPagination.
     *
     * @param array{page?: int, page_size?: int, search?: string, status?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function transactions(string $id, array $params = []): array
    {
        return $this->http->request('GET', "/customers/{$id}/transactions/", $params);
    }
}
