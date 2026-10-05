<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when upsert() is called with rows or columns it cannot compile.
 */
class UpsertException extends MarkoException
{
    public static function emptyRows(): self
    {
        return new self(
            message: 'Cannot upsert an empty set of rows',
            context: 'upsert() was called with an empty $rows array',
            suggestion: 'Pass at least one row, or skip the upsert() call when there is nothing to write',
        );
    }

    public static function emptyUniqueBy(): self
    {
        return new self(
            message: 'upsert() requires at least one conflict column in $uniqueBy',
            context: 'upsert() was called with an empty $uniqueBy array',
            suggestion: 'Name the column(s) of the unique index or primary key that identifies an existing row, e.g. [\'email\']',
        );
    }

    public static function columnSetMismatch(
        int $index,
    ): self {
        return new self(
            message: "Row $index has a different set of columns than row 0",
            context: 'upsert() compiles one multi-row INSERT, so every row must name the same columns in the same order',
            suggestion: 'Give every row the same keys (use null for a value you do not have)',
        );
    }

    public static function uniqueColumnMissing(
        string $column,
    ): self {
        return new self(
            message: "Conflict column '$column' is not one of the inserted columns",
            context: 'A $uniqueBy column must be present in every row so the database can find the existing row',
            suggestion: "Add '$column' to each row, or remove it from \$uniqueBy",
        );
    }

    public static function updateColumnMissing(
        string $column,
    ): self {
        return new self(
            message: "Update column '$column' is not one of the inserted columns",
            context: 'Each column in $update takes the value from the row that was proposed for insertion, so it must be present in the rows',
            suggestion: "Add '$column' to each row, or remove it from \$update",
        );
    }
}
