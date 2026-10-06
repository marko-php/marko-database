<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * The database detected a deadlock between this transaction and a concurrent
 * one, and rolled this transaction back to break it (PostgreSQL SQLSTATE
 * 40P01, MySQL error 1213).
 *
 * Retryable: see TransactionConflictException.
 */
class DeadlockException extends TransactionConflictException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function fromDriverError(
        PDOException $previous,
        string $sql,
        array $bindings,
    ): self {
        $driverMessage = self::redact(self::firstLine($previous->getMessage()), $bindings);

        return new self(
            message: "Deadlock detected, the transaction was rolled back: $driverMessage",
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            context: self::contextFor($sql, $bindings),
            suggestion: self::RETRY_SUGGESTION . '. To make deadlocks rarer, lock rows in the same order '
                . 'in every transaction',
            previous: $previous,
        );
    }
}
