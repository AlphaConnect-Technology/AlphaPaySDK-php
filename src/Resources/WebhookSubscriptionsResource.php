<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

final class WebhookSubscriptionsResource
{
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * @param array{page?: int, page_size?: int, webhook?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/merchant-webhook-subscriptions/', $params);
    }

    /** ATTENTION : dashboard-only -- 403 via clé API. */
    public function subscribe(string $webhookId, string $eventType): array
    {
        return $this->http->request('POST', '/merchant-webhook-subscriptions/', [], [
            'webhook' => $webhookId,
            'event_type' => $eventType,
        ]);
    }

    /** ATTENTION : dashboard-only -- 403 via clé API. */
    public function unsubscribe(string $subscriptionId): void
    {
        $this->http->request('DELETE', "/merchant-webhook-subscriptions/{$subscriptionId}/");
    }
}
