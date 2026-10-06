<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\Table;

/**
 * Settles expression defaults the database stores in its own spelling, before the schema diff runs.
 *
 * PostgreSQL stores `now() + interval '1 day'` as `(now() + '1 day'::interval)` and MySQL stores
 * `CONCAT('a', 'b')` as `concat(_utf8mb4'a',_utf8mb4'b')`, so comparing the text would report the column as
 * changed on every run. For each entity column whose Expression default differs from the database's, this asks
 * the introspector whether the database would store the entity's expression as the default the column already
 * has, and when it would, gives the entity column the database's default so DiffCalculator sees no change.
 *
 * The database is only asked about columns that would otherwise be modified for their default alone, so a
 * schema whose defaults already compare equal costs nothing. An introspector that does not implement
 * ExpressionDefaultMatcherInterface leaves the entity schema as it is.
 */
readonly class ExpressionDefaultCanonicalizer
{
    public function __construct(
        private IntrospectorInterface $introspector,
    ) {}

    /**
     * The entity schema, with every Expression default the database already stores (in its own spelling)
     * replaced by the database's default.
     *
     * @param array<string, Table> $entitySchema Tables defined by entities
     * @param array<string, Table> $databaseSchema Tables in the database
     * @return array<string, Table>
     * @throws ExpressionDefaultProbeException When the database cannot probe an entity's default expression
     */
    public function canonicalize(
        array $entitySchema,
        array $databaseSchema,
    ): array {
        if (!$this->introspector instanceof ExpressionDefaultMatcherInterface) {
            return $entitySchema;
        }

        $canonical = [];

        foreach ($entitySchema as $tableName => $entityTable) {
            $databaseTable = $databaseSchema[$tableName] ?? null;
            $canonical[$tableName] = $databaseTable === null
                ? $entityTable
                : $this->canonicalizeTable($this->introspector, $entityTable, $databaseTable);
        }

        return $canonical;
    }

    /**
     * @throws ExpressionDefaultProbeException
     */
    private function canonicalizeTable(
        ExpressionDefaultMatcherInterface $matcher,
        Table $entityTable,
        Table $databaseTable,
    ): Table {
        $databaseColumns = [];

        foreach ($databaseTable->columns as $column) {
            $databaseColumns[$column->name] = $column;
        }

        $columns = [];
        $changed = false;

        foreach ($entityTable->columns as $column) {
            $databaseColumn = $databaseColumns[$column->name] ?? null;

            if (
                $databaseColumn !== null
                && $this->differsOnlyInExpressionDefault($column, $databaseColumn)
                && $matcher->matchesStoredDefault($entityTable->name, $column->name, $column->default)
            ) {
                $column = $column->withDefault($databaseColumn->default);
                $changed = true;
            }

            $columns[] = $column;
        }

        if (!$changed) {
            return $entityTable;
        }

        return new Table(
            name: $entityTable->name,
            columns: $columns,
            indexes: $entityTable->indexes,
            foreignKeys: $entityTable->foreignKeys,
            unmanagedIndexes: $entityTable->unmanagedIndexes,
        );
    }

    /**
     * Whether the entity column declares an Expression default that does not compare equal to the database's
     * (non-null) default, and would otherwise be left alone by the diff.
     *
     * A column the diff modifies for another reason is not probed: its migration rewrites the column anyway,
     * and the SQL should then carry the expression the developer wrote.
     *
     * @phpstan-assert-if-true Expression $entityColumn->default
     */
    private function differsOnlyInExpressionDefault(
        Column $entityColumn,
        Column $databaseColumn,
    ): bool {
        return $entityColumn->default instanceof Expression
            && $databaseColumn->default !== null
            && !$entityColumn->hasSameDefaultAs($databaseColumn)
            && $entityColumn->withDefault($databaseColumn->default)->withoutUnique()->equals(
                $databaseColumn->withoutUnique(),
            );
    }
}
