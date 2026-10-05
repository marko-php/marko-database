<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Interface for database transaction management.
 *
 * Transactions nest. The outermost beginTransaction() opens a real
 * transaction; each nested call opens a savepoint. commit() and rollback()
 * close the innermost level, so a rollback inside a nested level undoes
 * only that level's work.
 */
interface TransactionInterface
{
    /**
     * Begin a new database transaction, or a savepoint when one is already open.
     */
    public function beginTransaction(): void;

    /**
     * Commit the innermost transaction level (releases the savepoint when nested).
     */
    public function commit(): void;

    /**
     * Roll back the innermost transaction level (to its savepoint when nested).
     */
    public function rollback(): void;

    /**
     * Check if a transaction is currently active.
     */
    public function inTransaction(): bool;

    /**
     * The number of open transaction levels: 0 outside a transaction, 1 inside
     * the outermost transaction, 2 inside the first savepoint, and so on.
     */
    public function transactionLevel(): int;

    /**
     * Execute a callback within a transaction.
     *
     * Automatically commits on success, rolls back on exception. Calls nest:
     * an inner call runs inside a savepoint of the outer transaction.
     *
     * @param callable $callback The callback to execute within the transaction
     * @return mixed The return value of the callback
     */
    public function transaction(callable $callback): mixed;

    /**
     * Run the callback once the outermost transaction commits.
     *
     * Outside a transaction the callback runs immediately. A callback
     * registered inside a level that rolls back never runs. An exception
     * thrown by the callback propagates to the caller of the outermost
     * commit(), after the data has already been committed.
     */
    public function afterCommit(callable $callback): void;

    /**
     * Run the callback if the transaction level it was registered in rolls back,
     * either directly or because an enclosing level rolls back.
     *
     * Outside a transaction there is nothing to roll back, so the callback is
     * discarded without running.
     */
    public function afterRollback(callable $callback): void;
}
