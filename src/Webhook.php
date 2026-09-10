<?php

declare(strict_types=1);

namespace AlphaPay;

use AlphaPay\Exceptions\AlphaPayWebhookSignatureException;

final class Webhook
{
    /** Fenêtre de tolérance par défaut, en secondes -- même valeur que côté API (apps.webhooks.services.SIGNATURE_TOLERANCE_SECONDS). */
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    private function __construct()
    {
        // Classe utilitaire statique, jamais instanciée.
    }

    /**
     * Vérifie qu'un webhook provient bien d'AlphaPay et n'a pas été rejoué.
     *
     * Reproduit exactement apps.webhooks.services.sign_payload côté API :
     * HMAC-SHA256 de "<timestamp>.<corps>", comparé en temps constant
     * (hash_equals) pour ne jamais fuiter d'information via le timing de la
     * comparaison.
     *
     * @param string $payload Corps BRUT de la requête (chaîne exacte reçue, avant tout json_decode -- la signature porte sur les octets exacts envoyés).
     * @param string $signature Valeur du header X-Webhook-Signature.
     * @param string|int $timestamp Valeur du header X-Webhook-Timestamp.
     * @param string $secret Secret de signature du webhook (visible une seule fois à la création/rotation dans le dashboard AlphaPay).
     * @param int $toleranceSeconds Fenêtre d'acceptation en secondes (défaut 300, comme recommandé par l'API).
     *
     * @return array{event: string, data: mixed} L'événement décodé -- jamais renvoyé avant vérification de la signature. N'appelez jamais json_decode($payload) vous-même avant ce contrôle : ce serait traiter un webhook non authentifié.
     *
     * @throws AlphaPayWebhookSignatureException Si la signature est invalide ou le timestamp hors fenêtre de tolérance (rejeu).
     */
    public static function verifySignature(
        string $payload,
        string $signature,
        string|int $timestamp,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS
    ): array {
        $ts = is_int($timestamp) ? $timestamp : filter_var($timestamp, FILTER_VALIDATE_INT);
        if ($ts === false) {
            throw new AlphaPayWebhookSignatureException("Timestamp de webhook invalide : \"{$timestamp}\".");
        }

        $expected = hash_hmac('sha256', "{$ts}.{$payload}", $secret);
        if (!hash_equals($expected, $signature)) {
            throw new AlphaPayWebhookSignatureException('Signature de webhook invalide — vérifiez le secret utilisé.');
        }

        $ageSeconds = abs(time() - $ts);
        if ($ageSeconds > $toleranceSeconds) {
            throw new AlphaPayWebhookSignatureException(
                "Timestamp de webhook hors fenêtre de tolérance ({$ageSeconds}s, limite {$toleranceSeconds}s) — rejeu potentiel."
            );
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AlphaPayWebhookSignatureException('Corps de webhook signé valide mais illisible (JSON invalide).');
        }

        if (!is_array($event)) {
            throw new AlphaPayWebhookSignatureException('Corps de webhook signé valide mais de forme inattendue (pas un objet JSON).');
        }

        return $event;
    }
}
