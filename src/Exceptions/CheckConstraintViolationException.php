<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * A statement wrote a row that fails a CHECK constraint. Usually a missing
 * validation rule, so it is not mapped to an HTTP status and surfaces as a 500.
 */
class CheckConstraintViolationException extends ConstraintViolationException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function fromDriverError(
        PDOException $previous,
        string $sql,
        array $bindings,
        ?string $constraintName = null,
        ?string $table = null,
        ?string $column = null,
    ): self {
        return new self(
            message: self::describe('Check', $constraintName, $table),
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            constraintName: $constraintName,
            table: $table,
            column: $column,
            context: self::contextFor($sql, $bindings),
            suggestion: 'Validate the values against the CHECK constraint before saving, or adjust the constraint '
                . 'in the schema',
            previous: $previous,
        );
    }
}
