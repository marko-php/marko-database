<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

interface ConnectionInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    /**
     * Execute a SELECT query and return results.
     *
     * @param string $sql The SQL query
     * @param array $bindings Parameter bindings
     * @return array<array<string, mixed>> Query results
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array;

    /**
     * Execute a non-SELECT statement (INSERT, UPDATE, DELETE).
     *
     * @param string $sql The SQL statement
     * @param array $bindings Parameter bindings
     * @return int Number of affected rows
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int;

    /**
     * Prepare a statement for execution.
     *
     * @param string $sql The SQL statement to prepare
     * @return StatementInterface The prepared statement
     */
    public function prepare(string $sql): StatementInterface;

    /**
     * Get the ID of the last inserted row.
     *
     * @return int The last insert ID
     */
    public function lastInsertId(): int;

    /**
     * Return the driver name for this connection (e.g. 'mysql', 'pgsql').
     *
     * Returns a per-driver constant so callers can branch on dialect
     * without requiring a live database connection.
     *
     * @return string Driver name
     */
    public function driverName(): string;

    /**
     * Whether this connection can read values back from an INSERT with
     * `INSERT ... RETURNING <columns>` through query().
     *
     * The repository uses it to read database-generated primary keys back.
     * Like driverName(), it must not require a live database connection.
     */
    public function supportsReturning(): bool;
}
