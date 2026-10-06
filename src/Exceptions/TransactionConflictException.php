<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

/**
 * The database aborted a transaction because it conflicted with a concurrent
 * one: a deadlock, or a serialization failure under REPEATABLE READ or
 * SERIALIZABLE isolation.
 *
 * None of the transaction's work can be kept: MySQL has already rolled it
 * back, and PostgreSQL has aborted it until the ROLLBACK that
 * TransactionInterface::transaction() sends. Running the whole transaction
 * again from the start is expected to succeed. Catch this class to retry any
 * conflict, or pass attempts to TransactionInterface::transaction() to have
 * the outermost transaction retried for you.
 *
 * Not mapped to an HTTP status: a conflict that is still failing after the
 * retries is a server-side error (500).
 */
abstract class TransactionConflictException extends QueryException
{
    protected const string RETRY_SUGGESTION = 'Run the whole transaction again: pass attempts to '
        . '$transaction->transaction(fn () => ..., attempts: 3) to retry it automatically, or catch '
        . 'TransactionConflictException around the outermost transaction and retry it yourself';

    /**
     * Always true: once the transaction is rolled back, running it again from
     * the start is safe.
     */
    public function isRetryable(): bool
    {
        return true;
    }
}
