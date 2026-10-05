<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use PDOException;

/**
 * A statement referenced a missing parent row, or deleted/updated a row that
 * other rows still reference.
 *
 * Rendered by the routing pipeline as 409 Conflict with a generic message:
 * the constraint, table and SQL stay in the exception (for logs) and are
 * never sent to the client.
 */
class ForeignKeyConstraintViolationException extends ConstraintViolationException implements HttpExceptionInterface
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
            message: self::describe('Foreign key', $constraintName, $table),
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            constraintName: $constraintName,
            table: $table,
            column: $column,
            context: self::contextFor($sql, $bindings),
            suggestion: 'Make sure the referenced row exists before inserting, and delete or detach referencing '
                . 'rows before deleting the row they point to; catch ForeignKeyConstraintViolationException to '
                . 'report the conflict to the user',
            previous: $previous,
        );
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return ['message' => 'Conflict.'];
    }
}
