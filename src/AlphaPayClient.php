<?php

declare(strict_types=1);

namespace AlphaPay;

use AlphaPay\Resources\ApiKeysResource;
use AlphaPay\Resources\BalancesResource;
use AlphaPay\Resources\CheckoutSessionsResource;
use AlphaPay\Resources\CustomersResource;
use AlphaPay\Resources\PaymentLinksResource;
use AlphaPay\Resources\SettlementsResource;
use AlphaPay\Resources\TransactionsResource;
use AlphaPay\Resources\WalletTransfersResource;
use AlphaPay\Resources\WebhookEndpointsResource;

/**
 * Client principal du SDK AlphaPay. Une instance = une clé API = un
 * marchand + un environnement (live/sandbox, déduit automatiquement du
 * préfixe de la clé -- sk_live_... ou sk_test_...).
 *
 * @example
 * $alphapay = new AlphaPayClient(getenv('ALPHAPAY_SECRET_KEY'));
 * $payment = $alphapay->transactions->payinInitialize([
 *     'amount' => 5000,
 *     'currency' => 'XOF',
 *     'country' => 'BJ',
 *     'network' => 'mtn_bj',
 *     'customer' => ['phone' => '+22900000000', 'full_name' => 'Client Test'],
 * ], idempotencyKey: true);
 */
final class AlphaPayClient
{
    public string $environment;
    /** Client HTTP bas niveau -- nécessaire à Pagination::paginate() pour suivre un lien "next" (URL absolue renvoyée par l'API), pas destiné à un usage direct en dehors de ce cas. */
    public Http $http;

    public TransactionsResource $transactions;
    public PaymentLinksResource $paymentLinks;
    public CheckoutSessionsResource $checkoutSessions;
    public CustomersResource $customers;
    public SettlementsResource $settlements;
    public WalletTransfersResource $walletTransfers;
    public BalancesResource $balances;
    public ApiKeysResource $apiKeys;
    public WebhookEndpointsResource $webhookEndpoints;

    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        int $timeout = 30,
        int $maxRetries = 2
    ) {
        $this->http = new Http($apiKey, $baseUrl, $timeout, $maxRetries);
        $this->environment = $this->http->environment;

        $this->transactions = new TransactionsResource($this->http);
        $this->paymentLinks = new PaymentLinksResource($this->http);
        $this->checkoutSessions = new CheckoutSessionsResource($this->http);
        $this->customers = new CustomersResource($this->http);
        $this->settlements = new SettlementsResource($this->http);
        $this->walletTransfers = new WalletTransfersResource($this->http);
        $this->balances = new BalancesResource($this->http);
        $this->apiKeys = new ApiKeysResource($this->http);
        $this->webhookEndpoints = new WebhookEndpointsResource($this->http);
    }
}
