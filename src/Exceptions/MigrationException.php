<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for migration-related errors.
 */
class MigrationException extends MarkoException
{
    public static function migrationFailed(
        string $migrationName,
        string $error,
    ): self {
        return new self(
            message: "Migration '$migrationName' failed: $error",
            context: "While running migration '$migrationName'",
            suggestion: 'Fix the migration error and try again. You may need to manually revert partial changes.',
        );
    }

    public static function partialIndexNotSupported(
        string $indexName,
        string $driver,
    ): self {
        return new self(
            message: "Index '$indexName' declares a WHERE predicate, but $driver does not support partial indexes",
            context: "While generating SQL for index '$indexName'",
            suggestion: "Remove 'where' from the #[Index] attribute, or create the index by hand in a migration and "
                . 'list its name in #[Table(unmanagedIndexes: [...])] so db:migrate leaves it alone.',
        );
    }

    public static function missingPreviousColumn(
        string $table,
        string $column,
    ): self {
        return new self(
            message: "Column '$table.$column' is modified, but the diff holds no previous definition for it",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Build the TableDiff with columnsToModifyFrom holding the database\'s current definition of '
                . 'every column in columnsToModify (DiffCalculator does this for you).',
        );
    }

    public static function emptyDefaultExpression(): self
    {
        return new self(
            message: 'A default expression cannot be empty',
            context: 'While creating a column default Expression',
            suggestion: "Pass the SQL of the default, such as new Expression('gen_random_uuid()'), or remove the "
                . 'default.',
        );
    }

    public static function rejectedDefaultExpression(
        string $table,
        string $column,
        string $expression,
        string $reason,
    ): self {
        return new self(
            message: "The database rejects the default expression \"$expression\" of column '$table.$column'",
            context: "While comparing the entity default of column '$table.$column' with the database: $reason",
            suggestion: 'Fix the SQL in the column\'s new Expression(...) default so the database accepts it as a '
                . 'column default.',
        );
    }

    public static function nothingToModify(
        string $table,
        string $column,
        string $driver,
    ): self {
        return new self(
            message: "Column '$table.$column' has no type, nullability or default change for $driver to apply",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Only call generateModifyColumn() when the type, nullability or default differs. A uniqueness '
                . 'change is applied through the index diff.',
        );
    }

    /**
     * @param list<string> $changes The changes the diff reported, such as `Modify column: title`
     */
    public static function emptyAlterMigration(
        string $table,
        array $changes,
    ): self {
        $changeList = implode('; ', $changes);

        return new self(
            message: "The schema diff reports changes to table '$table' ($changeList), but the SQL generator "
                . 'produced no statements for them',
            context: "While generating the alter migration for table '$table'",
            suggestion: 'The diff and the SQL generator disagree about this table, so no migration was written. Compare '
                . "the entity definition with the database's table definition and report the mismatch; until it is "
                . 'fixed, write any real change to this table by hand in a migration.',
        );
    }

    public static function columnChangeNotSupported(
        string $table,
        string $column,
        string $driver,
        string $change,
    ): self {
        return new self(
            message: "Cannot change the $change of column '$table.$column' in place on $driver",
            context: "While generating SQL to modify column '$table.$column'",
            suggestion: 'Write this change by hand in a migration (for example, add a new column, copy the data and '
                . 'drop the old one), then run db:migrate again.',
        );
    }

    public static function migrationNotFound(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration file for '$migrationName' not found",
            context: "While looking for migration '$migrationName'",
            suggestion: 'Ensure the migration file exists in the database/migrations/ directory.',
        );
    }

    public static function invalidMigration(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration '$migrationName' is invalid",
            context: "While loading migration '$migrationName'",
            suggestion: 'Migration files must return an instance of Migration class.',
        );
    }
}
