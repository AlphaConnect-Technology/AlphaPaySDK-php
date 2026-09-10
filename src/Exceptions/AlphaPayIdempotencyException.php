<?php

declare(strict_types=1);

namespace AlphaPay\Exceptions;

/** 409 - Idempotency-Key réutilisée avec un payload différent (cf. apps.core.mixins.IdempotencyMixin côté API). */
class AlphaPayIdempotencyException extends AlphaPayException
{
}
