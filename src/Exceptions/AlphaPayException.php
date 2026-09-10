<?php

declare(strict_types=1);

namespace AlphaPay\Exceptions;

use Exception;
use Throwable;

/**
 * Toute erreur API passe par apps.core.renderers.StandardJSONRenderer :
 * {"success": false, "error": <forme variable>, "code": <statut HTTP>}.
 * `error` n'a PAS une forme unique côté AlphaPayBack :
 *   - erreurs de validation DRF      -> {"champ": ["message", ...], ...}
 *   - erreurs métier personnalisées  -> {"message": "...", "code": "<code_machine>"}
 *   - erreurs d'authentification/permission -> {"detail": "..."}
 * getMessage() normalise ces trois formes en une seule chaîne lisible ;
 * getRaw() garde la forme originale pour qui a besoin du détail par champ.
 */
class AlphaPayException extends Exception
{
    private int $status;
    private ?string $errorCode;
    private mixed $raw;
    /** @var array<string, string[]>|null */
    private ?array $fieldErrors;
    private ?string $requestId;

    /**
     * @param array<string, string[]>|null $fieldErrors
     */
    public function __construct(
        string $message,
        int $status,
        ?string $errorCode = null,
        mixed $raw = null,
        ?array $fieldErrors = null,
        ?string $requestId = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->raw = $raw;
        $this->fieldErrors = $fieldErrors;
        $this->requestId = $requestId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** Code machine, quand l'API en fournit un (ex. "dashboard_only", "invalid_file_type") — absent sur les erreurs de validation par champ. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Corps d'erreur brut, tel que renvoyé par l'API. */
    public function getRaw(): mixed
    {
        return $this->raw;
    }

    /** Présent uniquement sur une erreur de validation DRF ({"champ": [...]}). */
    public function getFieldErrors(): ?array
    {
        return $this->fieldErrors;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Construit l'exception normalisée à partir de la réponse HTTP. `$body`
     * est déjà le contenu de la clé "error" de l'enveloppe (pas l'enveloppe entière).
     */
    public static function fromResponse(int $status, mixed $body, ?string $requestId = null): self
    {
        [$message, $errorCode, $fieldErrors] = self::interpretErrorBody($body);

        $class = match (true) {
            $status === 401 => AlphaPayAuthenticationException::class,
            $status === 403 => AlphaPayPermissionException::class,
            $status === 404 => AlphaPayNotFoundException::class,
            $status === 409 => AlphaPayIdempotencyException::class,
            $status === 429 => AlphaPayRateLimitException::class,
            $status === 400 || $status === 422 => AlphaPayValidationException::class,
            $status >= 500 => AlphaPayServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $errorCode, $body, $fieldErrors, $requestId);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?array<string, string[]>}
     */
    private static function interpretErrorBody(mixed $body): array
    {
        if ($body === null) {
            return ['Erreur AlphaPay inconnue.', null, null];
        }
        if (is_string($body)) {
            return [$body, null, null];
        }
        if (is_array($body)) {
            // {"message": "...", "code": "..."} -- erreurs métier personnalisées.
            if (isset($body['message']) && is_string($body['message'])) {
                $code = isset($body['code']) && is_string($body['code']) ? $body['code'] : null;
                return [$body['message'], $code, null];
            }
            // {"detail": "..."} -- erreurs d'auth/permission DRF standard.
            if (isset($body['detail']) && is_string($body['detail'])) {
                return [$body['detail'], null, null];
            }
            // {"champ": ["erreur", ...], ...} -- erreurs de validation DRF.
            $fieldErrors = [];
            foreach ($body as $field => $value) {
                if (is_string($field) && is_array($value) && array_reduce($value, fn ($carry, $v) => $carry && is_string($v), true) && $value !== []) {
                    $fieldErrors[$field] = $value;
                }
            }
            if ($fieldErrors !== []) {
                $summary = implode(' — ', array_map(
                    fn ($field, $errors) => "{$field}: " . implode(', ', $errors),
                    array_keys($fieldErrors),
                    array_values($fieldErrors)
                ));
                return [$summary, null, $fieldErrors];
            }
        }
        return ['Erreur AlphaPay inconnue.', null, null];
    }
}
