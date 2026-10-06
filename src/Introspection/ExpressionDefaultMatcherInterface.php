<?php

declare(strict_types=1);

namespace Marko\Database\Introspection;

use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Schema\Expression;

/**
 * An introspector that can ask the database how it would store an expression default.
 *
 * Databases store a rewritten (deparsed) form of an expression default, not the text a migration wrote:
 * PostgreSQL turns `now() + interval '1 day'` into `(now() + '1 day'::interval)`, MySQL adds charset
 * introducers to string literals. The schema diff uses this to tell a default the database merely respelled
 * from one that really changed. It is a separate interface so introspectors that do not implement it keep
 * working; their expression defaults are compared by Expression::equals() alone.
 */
interface ExpressionDefaultMatcherInterface
{
    /**
     * Whether the column would report the default it has now if it were declared with $expression.
     *
     * Implementations declare a throwaway column of the same type with the expression as its default, read
     * back what the database stored, and compare it with the column's stored default. The throwaway column
     * never outlives the call and the real schema is never changed.
     *
     * @throws ExpressionDefaultProbeException When the database rejects the expression, or the connection may not create the temporary table
     */
    public function matchesStoredDefault(
        string $table,
        string $column,
        Expression $expression,
    ): bool;
}
