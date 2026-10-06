<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Diff\Fixtures;

use Closure;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\Table;

/**
 * An introspector whose expression-default answers come from a closure, recording every probe it is asked for.
 */
class CountingMatcherIntrospector implements IntrospectorInterface, ExpressionDefaultMatcherInterface
{
    /**
     * @var list<array{table: string, column: string, sql: string}>
     */
    public array $probes = [];

    /**
     * @param Closure(string, string, Expression): bool $matches
     * @param array<string, Table> $tables The tables the introspector reports
     */
    public function __construct(
        private readonly Closure $matches,
        private readonly array $tables = [],
    ) {}

    /**
     * @throws MigrationException
     */
    public function matchesStoredDefault(
        string $table,
        string $column,
        Expression $expression,
    ): bool {
        $this->probes[] = ['table' => $table, 'column' => $column, 'sql' => $expression->sql];

        return ($this->matches)($table, $column, $expression);
    }

    public function getTables(): array
    {
        return array_keys($this->tables);
    }

    public function getTable(
        string $name,
    ): ?Table {
        return $this->tables[$name] ?? null;
    }

    public function tableExists(
        string $name,
    ): bool {
        return isset($this->tables[$name]);
    }

    public function getColumns(
        string $table,
    ): array {
        return $this->tables[$table]->columns ?? [];
    }

    public function getIndexes(
        string $table,
    ): array {
        return [];
    }

    public function getForeignKeys(
        string $table,
    ): array {
        return [];
    }

    public function getPrimaryKey(
        string $table,
    ): array {
        return [];
    }
}
