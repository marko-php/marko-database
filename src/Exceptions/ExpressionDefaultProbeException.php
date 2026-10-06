<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

/**
 * The database could not declare an entity's expression default on a temporary probe column, so the schema diff
 * cannot tell whether the column's stored default matches it.
 *
 * Thrown by ExpressionDefaultMatcherInterface implementations. The cause is either the expression itself (the
 * database rejects it for the column's type) or the connection's user lacking the privilege to create a temporary
 * table; the database's message says which.
 */
class ExpressionDefaultProbeException extends MigrationException
{
    public static function rejected(
        string $table,
        string $column,
        string $expression,
        string $reason,
    ): self {
        return new self(
            message: "The database rejected the default expression \"$expression\" of column '$table.$column': "
                . $reason,
            context: "While comparing the entity default of column '$table.$column' with the database's on a "
                . 'temporary probe table',
            suggestion: 'Fix the SQL in the column\'s new Expression(...) default so the database accepts it as a '
                . 'column default. If the error is a missing privilege, grant the connection\'s user CREATE '
                . 'TEMPORARY TABLES (MySQL/MariaDB) or TEMPORARY on the database (PostgreSQL).',
        );
    }
}
