<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for transaction-related errors.
 */
class TransactionException extends MarkoException
{
    public static function connectionDoesNotSupportTransactions(
        string $connectionClass,
    ): self {
        return new self(
            message: "Connection '$connectionClass' does not support transactions",
            context: 'Resolving TransactionInterface returns the shared ConnectionInterface instance, which must also implement TransactionInterface',
            suggestion: 'Bind ConnectionInterface to a connection that implements TransactionInterface, or bind TransactionInterface explicitly',
        );
    }

    public static function notInTransaction(): self
    {
        return new self(
            message: 'No active transaction',
            context: 'Attempted to commit or rollback when no transaction is active',
            suggestion: 'Call beginTransaction() before commit() or rollback()',
        );
    }
}
