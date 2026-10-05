<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Throwable;

/**
 * A statement violated an integrity constraint.
 *
 * Catch a subclass (unique, foreign key, not null, check) to handle one kind
 * of violation, or this class to handle them all. The constraint, table and
 * column are parsed from the driver error when the driver reports them, and
 * are null otherwise.
 */
class ConstraintViolationException extends QueryException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(
        string $message,
        string $sql = '',
        array $bindings = [],
        ?string $sqlState = null,
        private readonly ?string $constraintName = null,
        private readonly ?string $table = null,
        private readonly ?string $column = null,
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            sql: $sql,
            bindings: $bindings,
            sqlState: $sqlState,
            context: $context,
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public function constraintName(): ?string
    {
        return $this->constraintName;
    }

    public function table(): ?string
    {
        return $this->table;
    }

    public function column(): ?string
    {
        return $this->column;
    }

    /**
     * "{Kind} constraint 'name' violated on table 'table'", leaving out the
     * parts the driver did not report.
     */
    protected static function describe(
        string $kind,
        ?string $constraintName,
        ?string $table,
    ): string {
        $message = $constraintName !== null
            ? "$kind constraint '$constraintName' violated"
            : "$kind constraint violated";

        return $table !== null ? "$message on table '$table'" : $message;
    }
}
