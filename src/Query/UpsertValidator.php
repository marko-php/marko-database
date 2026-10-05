<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Exceptions\UpsertException;

/**
 * Shared validation for QueryBuilderInterface::upsert() implementations.
 *
 * Drivers compile the dialect-specific SQL; this class makes sure every
 * driver rejects the same malformed input with the same loud error.
 */
class UpsertValidator
{
    /**
     * Validate the upsert input and resolve the columns to update on conflict.
     *
     * When $update is null, every inserted column except the $uniqueBy
     * columns is updated.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     * @param array<int, string>|null $update
     * @return list<string> The columns to update when a row already exists
     * @throws InvalidColumnException|UpsertException
     */
    public static function validate(
        array $rows,
        array $uniqueBy,
        ?array $update,
    ): array {
        if ($rows === []) {
            throw UpsertException::emptyRows();
        }

        if ($uniqueBy === []) {
            throw UpsertException::emptyUniqueBy();
        }

        $rows = array_values($rows);
        $columns = array_keys($rows[0]);

        foreach ($columns as $column) {
            IdentifierValidator::assertValidIdentifier((string) $column);
        }

        foreach ($rows as $index => $row) {
            if (array_keys($row) !== $columns) {
                throw UpsertException::columnSetMismatch($index);
            }
        }

        foreach ($uniqueBy as $column) {
            if (!in_array($column, $columns, true)) {
                throw UpsertException::uniqueColumnMissing($column);
            }
        }

        if ($update === null) {
            return array_values(array_filter(
                array_map('strval', $columns),
                fn (string $column): bool => !in_array($column, $uniqueBy, true),
            ));
        }

        foreach ($update as $column) {
            if (!in_array($column, $columns, true)) {
                throw UpsertException::updateColumnMissing($column);
            }
        }

        return array_values($update);
    }
}
