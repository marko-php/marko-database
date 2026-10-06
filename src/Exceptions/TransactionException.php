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

    public static function cannotRunPendingAfterCommitCallbacks(
        string $connectionClass,
    ): self {
        return new self(
            message: "Connection '$connectionClass' cannot run pending after-commit callbacks",
            context: 'Running after-commit callbacks queued inside a transaction that is never committed (a RefreshDatabase test)',
            suggestion: 'Use a connection that implements Marko\Database\Connection\PendingAfterCommitInterface (the pgsql and mysql drivers do), or assert on the callbacks by committing a real transaction',
        );
    }

    public static function invalidAttempts(
        int $attempts,
    ): self {
        return new self(
            message: "A transaction needs at least 1 attempt, $attempts attempts given",
            context: 'Calling TransactionInterface::transaction() with an attempts argument below 1',
            suggestion: 'Pass attempts: 1 (the default) to run the transaction once, or a higher number to '
                . 'retry it on a deadlock or serialization failure',
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
