<?php

declare(strict_types=1);

namespace AlphaPay\Exceptions;

class AlphaPayRateLimitException extends AlphaPayException
{
    private ?int $retryAfter = null;

    /** Secondes à attendre avant de réessayer, quand l'API le précise (header Retry-After). */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function setRetryAfter(?int $retryAfter): void
    {
        $this->retryAfter = $retryAfter;
    }
}
