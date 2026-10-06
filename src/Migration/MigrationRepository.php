<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Database\Connection\ConnectionInterface;

/**
 * Tracks applied migrations in the database.
 */
class MigrationRepository
{
    private const string TABLE = 'migrations';

    /**
     * Create the migrations table if it doesn't exist.
     */
    public function createTable(
        ConnectionInterface $connection,
    ): void {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$this->table($connection)} (
                name VARCHAR(255) NOT NULL PRIMARY KEY,
                batch INT NOT NULL
            )
            SQL;

        $connection->execute($sql);
    }

    /**
     * Record a migration as applied.
     */
    public function record(
        ConnectionInterface $connection,
        string $name,
        int $batch,
    ): void {
        $connection->execute(
            "INSERT INTO {$this->table($connection)} (name, batch) VALUES (?, ?)",
            [$name, $batch],
        );
    }

    /**
     * Delete a migration record.
     */
    public function delete(
        ConnectionInterface $connection,
        string $name,
    ): void {
        $connection->execute(
            "DELETE FROM {$this->table($connection)} WHERE name = ?",
            [$name],
        );
    }

    /**
     * Get all applied migration names.
     *
     * @return array<string>
     */
    public function getApplied(
        ConnectionInterface $connection,
    ): array {
        $rows = $connection->query("SELECT name FROM {$this->table($connection)} ORDER BY name");

        return array_column($rows, 'name');
    }

    /**
     * Get all applied migrations with their batch numbers.
     *
     * @return array<array{name: string, batch: int}>
     */
    public function getAppliedWithBatch(
        ConnectionInterface $connection,
    ): array {
        return $connection->query("SELECT name, batch FROM {$this->table($connection)} ORDER BY name");
    }

    /**
     * Get the next batch number.
     */
    public function getNextBatchNumber(
        ConnectionInterface $connection,
    ): int {
        $result = $connection->query("SELECT MAX(batch) as max_batch FROM {$this->table($connection)}");

        $maxBatch = $result[0]['max_batch'] ?? null;

        return ($maxBatch ?? 0) + 1;
    }

    /**
     * Get migrations from the last batch.
     *
     * @return array<string>
     */
    public function getLastBatchMigrations(
        ConnectionInterface $connection,
    ): array {
        $result = $connection->query("SELECT MAX(batch) as max_batch FROM {$this->table($connection)}");

        $maxBatch = $result[0]['max_batch'] ?? null;

        if ($maxBatch === null) {
            return [];
        }

        $rows = $connection->query(
            "SELECT name FROM {$this->table($connection)} WHERE batch = ? ORDER BY name DESC",
            [$maxBatch],
        );

        return array_column($rows, 'name');
    }

    /**
     * The migrations table name quoted for the connection's SQL dialect.
     */
    private function table(
        ConnectionInterface $connection,
    ): string {
        return $connection->quoteIdentifier(self::TABLE);
    }
}
