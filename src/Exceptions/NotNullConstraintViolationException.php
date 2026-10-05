<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * A statement wrote NULL into a NOT NULL column. Usually a programming error,
 * so it is not mapped to an HTTP status and surfaces as a 500.
 */
class NotNullConstraintViolationException extends ConstraintViolationException
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
        $message = $column !== null
            ? "Not-null constraint violated: column '$column'"
                . ($table !== null ? " on table '$table'" : '')
                . ' cannot be null'
            : self::describe('Not-null', $constraintName, $table);
        $target = $column !== null ? "'$column'" : 'the column';

        return new self(
            message: $message,
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            constraintName: $constraintName,
            table: $table,
            column: $column,
            context: self::contextFor($sql, $bindings),
            suggestion: "Set a value for $target before saving, give the column a default, or make it nullable",
            previous: $previous,
        );
    }
}
