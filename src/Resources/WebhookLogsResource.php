<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

final class WebhookLogsResource
{
    public function __construct(private readonly Http $http)
    {
    }

    /**
     * @param array{page?: int, page_size?: int, webhook?: string, status?: string, event_type?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/webhook-logs/', $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/webhook-logs/{$id}/");
    }

    /** ATTENTION : dashboard-only -- 403 via clé API. Rejoue immédiatement cette livraison (nouvelle tentative hors du calendrier de retry automatique). */
    public function resend(string $id): array
    {
        return $this->http->request('POST', "/webhook-logs/{$id}/resend/");
    }
}
