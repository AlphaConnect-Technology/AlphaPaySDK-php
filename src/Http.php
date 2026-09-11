<?php

declare(strict_types=1);

namespace AlphaPay;

use AlphaPay\Exceptions\AlphaPayConnectionException;
use AlphaPay\Exceptions\AlphaPayException;
use AlphaPay\Exceptions\AlphaPayRateLimitException;
use AlphaPay\Exceptions\AlphaPayServerException;

/**
 * Client HTTP bas niveau, partagé par toutes les ressources. Gère
 * l'authentification, l'enveloppe standard {success, data, code}, les
 * retries avec backoff sur 429/5xx/erreur réseau, et le mapping vers des
 * exceptions typées (cf. Exceptions/) — chaque ressource (Transactions,
 * PaymentLinks, ...) n'a plus qu'à décrire le endpoint, jamais la mécanique réseau.
 */
final class Http
{
    private const DEFAULT_BASE_URL = 'https://api.alphapay.me/api/v1';
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_MAX_RETRIES = 2;

    private string $apiKey;
    public string $baseUrl;
    public string $environment;
    private int $timeout;
    private int $maxRetries;

    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        int $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('AlphaPayClient: `apiKey` est requis.');
        }
        $this->apiKey = $apiKey;
        $this->environment = $this->startsWith($apiKey, 'sk_live_') ? 'live' : 'sandbox';
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body
     * @param string|bool|null $idempotencyKey
     * @return mixed
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        $idempotencyKey = null
    ) {
        // `$path` est une URL absolue quand on suit un lien "next"/"previous"
        // renvoyé tel quel par l'API (cf. Pagination::paginate()) -- déjà
        // complète, avec sa propre query string ; ne jamais la préfixer par
        // baseUrl ni y rajouter `$query` par-dessus.
        $url = $this->startsWith($path, 'http://') || $this->startsWith($path, 'https://')
            ? $path
            : $this->baseUrl . $path . $this->buildQueryString($query);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        $jsonBody = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        }
        if ($idempotencyKey !== null && $idempotencyKey !== false) {
            $key = $idempotencyKey === true ? $this->randomIdempotencyKey() : $idempotencyKey;
            $headers[] = 'Idempotency-Key: ' . $key;
        }

        $attempt = 0;
        while (true) {
            try {
                return $this->attempt($method, $url, $headers, $jsonBody);
            } catch (AlphaPayRateLimitException|AlphaPayServerException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                $retryAfterMs = ($e instanceof AlphaPayRateLimitException && $e->getRetryAfter() !== null)
                    ? $e->getRetryAfter() * 1000
                    : $this->backoffMs($attempt);
                usleep($retryAfterMs * 1000);
                $attempt++;
            } catch (AlphaPayConnectionException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                usleep($this->backoffMs($attempt) * 1000);
                $attempt++;
            }
        }
    }

    /**
     * Pour les rares endpoints qui répondent en binaire plutôt qu'en JSON
     * (ex. GET /transactions/{id}/invoice/, application/pdf, et
     * /transactions/export/, text/csv) -- bypass l'enveloppe standard,
     * jamais utilisée par un endpoint qui en renvoie une.
     *
     * @return array{data: string, contentType: ?string, filename: ?string}
     */
    public function requestBinary(string $path): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/pdf, text/csv, application/octet-stream',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AlphaPayConnectionException("Impossible de joindre l'API AlphaPay : {$error}", 0);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);

        if ($status >= 400) {
            // Une erreur sur cet endpoint reste en JSON (cf. InvoiceError côté API) --
            // on retombe sur le mapping JSON normal pour un message exploitable.
            $decoded = json_decode($responseBody, true);
            $errorBody = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : $decoded;
            throw AlphaPayException::fromResponse($status, $errorBody, $this->extractHeader($rawHeaders, 'X-Request-Id'));
        }

        return [
            'data' => $responseBody,
            'contentType' => $this->extractHeader($rawHeaders, 'Content-Type'),
            'filename' => $this->extractFilename($this->extractHeader($rawHeaders, 'Content-Disposition')),
        ];
    }

    /** @return mixed */
    private function attempt(string $method, string $url, array $headers, ?string $jsonBody)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            $isTimeout = $errno === CURLE_OPERATION_TIMEDOUT;
            throw new AlphaPayConnectionException(
                $isTimeout ? "Requête AlphaPay expirée." : "Impossible de joindre l'API AlphaPay : {$error}",
                0
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $requestId = $this->extractHeader($rawHeaders, 'X-Request-Id');

        $json = null;
        if ($body !== '') {
            try {
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // Réponse non-JSON (page d'erreur d'un proxy en amont) -- traité
                // ci-dessous comme un succès sans corps ou une erreur au message générique.
            }
        }

        if ($status < 400) {
            return is_array($json) && array_key_exists('data', $json) ? $json['data'] : $json;
        }

        $errorBody = is_array($json) && array_key_exists('error', $json) ? $json['error'] : $json;
        $exception = AlphaPayException::fromResponse($status, $errorBody, $requestId);

        if ($exception instanceof AlphaPayRateLimitException) {
            $retryAfter = $this->extractHeader($rawHeaders, 'Retry-After');
            $exception->setRetryAfter($retryAfter !== null ? (int) $retryAfter : null);
        }
        throw $exception;
    }

    /** @param array<string, mixed> $query */
    private function buildQueryString(array $query): string
    {
        $filtered = array_filter($query, static fn ($v) => $v !== null);
        if ($filtered === []) {
            return '';
        }
        return '?' . http_build_query($filtered);
    }

    private function backoffMs(int $attempt): int
    {
        // Backoff exponentiel + gigue : 400-600ms, 800-1200ms, 1600-2400ms...
        $base = 400 * (2 ** $attempt);
        return (int) ($base + random_int(0, (int) ($base * 0.5)));
    }

    private function randomIdempotencyKey(): string
    {
        return 'idem_' . bin2hex(random_bytes(16));
    }

    // Équivalent de str_starts_with() (PHP 8.0+), indisponible en PHP 7.4 --
    // ce SDK vise 7.4 pour rester compatible avec l'hébergement WordPress/
    // WooCommerce mutualisé (cf. composer.json).
    private function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    private function extractHeader(string $rawHeaders, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $rawHeaders, $matches) === 1) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extractFilename(?string $contentDisposition): ?string
    {
        if ($contentDisposition === null) {
            return null;
        }
        if (preg_match('/filename="?([^";]+)"?/', $contentDisposition, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }
}
