<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a row lock (lockForUpdate(), sharedLock()) is misused.
 */
class LockException extends MarkoException
{
    public static function outsideTransaction(
        string $table,
    ): self {
        return new self(
            message: "Cannot lock rows of '$table' outside a transaction",
            context: 'A SELECT with lockForUpdate() or sharedLock() ran while no transaction was open, so the lock would be released as soon as the statement finished',
            suggestion: 'Run the locking read and the writes that depend on it inside $transaction->transaction(function () { ... })',
        );
    }

    public static function modifierWithoutLock(
        string $modifier,
    ): self {
        return new self(
            message: "$modifier() requires lockForUpdate() or sharedLock()",
            context: "The query called $modifier() but never asked for a row lock",
            suggestion: "Call lockForUpdate() or sharedLock() on the same query, or remove $modifier()",
        );
    }

    public static function conflictingModifiers(): self
    {
        return new self(
            message: 'skipLocked() and noWait() cannot be combined',
            context: 'A query called both skipLocked() and noWait()',
            suggestion: 'Use skipLocked() to ignore locked rows, or noWait() to fail immediately on a locked row, not both',
        );
    }

    public static function unsupportedOperation(
        string $operation,
    ): self {
        return new self(
            message: "Row locks cannot be used with $operation",
            context: "A query with lockForUpdate() or sharedLock() was executed through $operation",
            suggestion: 'Lock rows with get() or first() on a plain SELECT, then aggregate or combine the locked rows in PHP or in a separate query',
        );
    }
}
