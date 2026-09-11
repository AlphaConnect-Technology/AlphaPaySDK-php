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
    /** @var mixed */
    private $raw;
    /** @var array<string, string[]>|null */
    private ?array $fieldErrors;
    private ?string $requestId;

    /**
     * @param mixed $raw
     * @param array<string, string[]>|null $fieldErrors
     */
    public function __construct(
        string $message,
        int $status,
        ?string $errorCode = null,
        $raw = null,
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

    /**
     * Corps d'erreur brut, tel que renvoyé par l'API.
     * @return mixed
     */
    public function getRaw()
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
     *
     * @param mixed $body
     */
    public static function fromResponse(int $status, $body, ?string $requestId = null): self
    {
        [$message, $errorCode, $fieldErrors] = self::interpretErrorBody($body);

        if ($status === 401) {
            $class = AlphaPayAuthenticationException::class;
        } elseif ($status === 403) {
            $class = AlphaPayPermissionException::class;
        } elseif ($status === 404) {
            $class = AlphaPayNotFoundException::class;
        } elseif ($status === 409) {
            $class = AlphaPayIdempotencyException::class;
        } elseif ($status === 429) {
            $class = AlphaPayRateLimitException::class;
        } elseif ($status === 400 || $status === 422) {
            $class = AlphaPayValidationException::class;
        } elseif ($status >= 500) {
            $class = AlphaPayServerException::class;
        } else {
            $class = self::class;
        }

        return new $class($message, $status, $errorCode, $body, $fieldErrors, $requestId);
    }

    /**
     * @param mixed $body
     * @return array{0: string, 1: ?string, 2: ?array<string, string[]>}
     */
    private static function interpretErrorBody($body): array
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
