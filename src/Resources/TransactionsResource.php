<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Paiements (encaissements), retraits (payouts) et leur historique.
 * payin()/payout() sont volontairement séparés du CRUD list()/get() -- ce
 * sont des actions (initier un mouvement d'argent), pas des ressources REST
 * classiques, cf. /payments/*​ et /payouts/*​ côté API.
 */
final class TransactionsResource
{
    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * PAS de "page" dans $params -- TransactionListView est paginée par
     * curseur côté API (pagination_class = CreatedAtCursorPagination),
     * vérifié contre le code réel : "page" n'a aucun effet ici, la réponse
     * n'a pas de clé "count". Utilisez Pagination::paginate() pour tout
     * parcourir, pas une boucle sur un numéro de page.
     *
     * @param array{page_size?: int, search?: string, status?: string, transaction_type?: string, flow_direction?: string, country?: string, network?: string, customer?: string, created_at__gte?: string, created_at__lte?: string} $params
     * @return array{next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/transactions/', $params);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/transactions/{$id}/");
    }

    /**
     * Télécharge la facture PDF -- réponse binaire authentifiée, 409 hors
     * INBOUND+SUCCESS (cf. apps.transactions.services.invoice.generate_invoice_pdf).
     *
     * @return array{data: string, contentType: ?string, filename: ?string}
     */
    public function downloadInvoice(string $id): array
    {
        return $this->http->requestBinary("/transactions/{$id}/invoice/");
    }

    /**
     * Export CSV -- mêmes filtres que list(), sans limite de lignes ni pagination.
     *
     * @param array<string, mixed> $params
     * @return array{data: string, contentType: ?string, filename: ?string}
     */
    public function export(array $params = []): array
    {
        $qs = http_build_query(array_filter($params, static fn ($v) => $v !== null));
        return $this->http->requestBinary('/transactions/export/' . ($qs !== '' ? "?{$qs}" : ''));
    }

    /**
     * Encaisse directement (push USSD/mobile money) sans page de checkout à
     * suivre. `$idempotencyKey` fortement recommandée : un doublon pousse un
     * second prompt de paiement vers le client final.
     *
     * @param array{merchant?: string, amount: int|float|string, currency: string, country: string, description?: string, customer: array{full_name?: string, email?: string, phone: string}, network: string, return_url?: string, metadata?: array<string, mixed>, fee_charge_mode?: ?string, preferred_gateway?: string, otp?: string} $params
     * @param string|bool|null $idempotencyKey
     * @return array{message: string, id: string, status: string, checkout_url: string, instructions: mixed}
     */
    public function payinInitialize(array $params, $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/payments/softpay/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function payinVerify(string $paymentId): array
    {
        return $this->http->request('GET', "/payments/{$paymentId}/verify/");
    }

    /**
     * @param array{preferred_gateway?: string} $params
     * @param string|bool|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function payinRetry(string $paymentId, array $params = [], $idempotencyKey = null): array
    {
        return $this->http->request('POST', "/payments/{$paymentId}/retry/", [], $params, $idempotencyKey);
    }

    /** Réseaux exigeant une confirmation en 2 temps (ex. Wizall Sénégal, Coris Bénin). */
    public function payinConfirmOtp(string $paymentId, string $otp): array
    {
        return $this->http->request('POST', "/payments/{$paymentId}/confirm-otp/", [], ['otp' => $otp]);
    }

    /**
     * Déclenche un retrait -- débite immédiatement le wallet marchand
     * (réservation de solde). `$idempotencyKey` fortement recommandée : sans
     * elle, un doublon débite deux fois le MÊME wallet.
     *
     * @param array{merchant?: string, amount: int|float|string, currency: string, country: string, description?: string, customer: array{full_name?: string, email?: string, phone: string}, metadata?: array<string, mixed>, method: string, recipient: array<string, mixed>, fee_charge_mode?: ?string, preferred_gateway?: string} $params
     * @param string|bool|null $idempotencyKey
     * @return array{message: string, id: string}
     */
    public function payoutInitialize(array $params, $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/payouts/initialize/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function payoutVerify(string $payoutId): array
    {
        return $this->http->request('GET', "/payouts/{$payoutId}/verify/");
    }
}
