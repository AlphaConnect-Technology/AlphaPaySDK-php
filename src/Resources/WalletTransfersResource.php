<?php

declare(strict_types=1);

namespace AlphaPay\Resources;

use AlphaPay\Http;

/**
 * Transferts entre les wallets multi-pays d'un même marchand (avec
 * conversion automatique si les devises diffèrent). L'argent ne quitte
 * jamais la plateforme -- usage typique : approvisionner le wallet du pays
 * d'un bénéficiaire avant un payout Mobile Money.
 *
 * Accessible via clé API depuis le 2026-09-24 (auparavant 403
 * "dashboard_only") :
 *   - create() exige une clé de scope PAYOUT ou BOTH -- une clé PAYIN reçoit
 *     un 403 AlphaPayPermissionException, getErrorCode() === "api_key_scope_forbidden".
 *   - list()/get() : toute clé du marchand.
 *
 * Cycle de vie (STATUS_*) : PENDING -> COMPLETED ou REJECTED. Un transfert
 * n'est COMPLETED immédiatement que si l'auto-approbation est activée pour
 * le marchand (réglage AlphaPay, champ `auto_approved_via` = "GLOBAL" ou
 * "MERCHANT") ; sinon il reste PENDING jusqu'à validation par un admin
 * AlphaPay -- suivez les webhooks wallet_transfer.completed/.rejected
 * plutôt que d'interroger get() en boucle.
 *
 * Frais : prélevés sur le montant CONVERTI -- `to_amount` est déjà net,
 * `fee_amount`/`fee_percent_applied` détaillent le frais retenu.
 */
final class WalletTransfersResource
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_REJECTED = 'REJECTED';

    private Http $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * `from_country`/`to_country` : code ISO2 (ex. "CM"), comme dans les réponses.
     *
     * @param array{page?: int, page_size?: int, search?: string, status?: string, from_country?: string, to_country?: string} $params
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array<string, mixed>>}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/wallet-transfers/', $params);
    }

    /**
     * Réserve `from_amount` sur le wallet source. Idempotency-Key fortement
     * recommandée (`true` en génère une) : sans elle, un retry réseau
     * réserverait deux fois le montant. Réutiliser une clé avec un contenu
     * différent lève AlphaPayIdempotencyException (409).
     *
     * Refus métier -> AlphaPayValidationException (422), getErrorCode() :
     * "same_country", "transfer_already_pending" (un seul transfert PENDING
     * à la fois par marchand), "wallet_frozen", "insufficient_balance".
     *
     * @param array{from_country: string, to_country: string, from_amount: int|float|string} $params
     * @param string|bool|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function create(array $params, $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/wallet-transfers/', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', "/wallet-transfers/{$id}/");
    }
}
