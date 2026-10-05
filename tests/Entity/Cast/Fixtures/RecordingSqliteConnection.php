<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use PDO;
use RuntimeException;

/**
 * In-memory SQLite connection that records every executed statement and its bindings.
 */
class RecordingSqliteConnection implements ConnectionInterface
{
    /**
     * @var array<int, array{sql: string, bindings: array<int, mixed>}>
     */
    public array $executed = [];

    private readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executed[] = ['sql' => $sql, 'bindings' => $bindings];

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented for this test connection');
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    /**
     * Run raw DDL (CREATE TABLE) without recording it.
     */
    public function createTable(
        string $ddl,
    ): void {
        $this->pdo->exec($ddl);
    }

    /**
     * Map the columns of the most recent INSERT to their bound values.
     *
     * @return array<string, mixed>
     */
    public function lastInsertValues(): array
    {
        return $this->lastValuesFor('INSERT');
    }

    /**
     * Map the SET columns of the most recent UPDATE to their bound values.
     *
     * @return array<string, mixed>
     */
    public function lastUpdateValues(): array
    {
        return $this->lastValuesFor('UPDATE');
    }

    /**
     * @return array<string, mixed>
     */
    private function lastValuesFor(
        string $verb,
    ): array {
        $matches = array_filter(
            $this->executed,
            fn (array $entry): bool => str_starts_with($entry['sql'], $verb),
        );

        if ($matches === []) {
            throw new RuntimeException("No $verb statement was executed");
        }

        $entry = array_last($matches);

        if ($verb === 'INSERT') {
            preg_match('/\(([^)]*)\) VALUES/', $entry['sql'], $found);
            $columns = array_map('trim', explode(',', $found[1]));
        } else {
            preg_match('/SET (.*) WHERE/', $entry['sql'], $found);
            $columns = array_map(
                fn (string $clause): string => trim(explode('=', $clause)[0]),
                explode(',', $found[1]),
            );
        }

        return array_combine($columns, array_slice($entry['bindings'], 0, count($columns)));
    }
}
