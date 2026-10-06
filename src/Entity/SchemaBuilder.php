<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Database\Exceptions\EntityException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

/**
 * Converts EntityMetadata to Schema value objects.
 */
class SchemaBuilder
{
    /**
     * Type synonyms the SQL generators accept, mapped to the name introspectors report, so a column declared as
     * `int` compares equal to the `integer` column the database holds.
     *
     * @var array<string, string>
     */
    private const array TYPE_SYNONYMS = [
        'int' => 'integer',
        'bool' => 'boolean',
        'string' => 'varchar',
    ];

    /**
     * Build a Table schema from EntityMetadata.
     *
     * @throws EntityException When a declared index name is longer than 63 bytes
     */
    public function build(
        EntityMetadata $metadata,
    ): Table {
        $columns = array_map(
            fn (ColumnMetadata $col) => $this->buildColumn($col),
            $metadata->columns,
        );

        $indexes = array_map(
            fn (IndexMetadata $idx) => $this->buildIndex($idx, $metadata->entityClass, $metadata->tableName),
            $metadata->indexes,
        );

        $foreignKeys = $this->buildForeignKeys($metadata->tableName, $metadata->columns);

        return new Table(
            name: $metadata->tableName,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            unmanagedIndexes: $metadata->unmanagedIndexes,
        );
    }

    /**
     * Build a Column schema from ColumnMetadata.
     */
    public function buildColumn(
        ColumnMetadata $metadata,
    ): Column {
        return new Column(
            name: $metadata->name,
            type: self::TYPE_SYNONYMS[strtolower($metadata->type)] ?? $metadata->type,
            length: $metadata->length,
            nullable: $metadata->nullable,
            default: $metadata->default,
            unique: $metadata->unique,
            primaryKey: $metadata->primaryKey,
            autoIncrement: $metadata->autoIncrement,
            references: $metadata->references,
            onDelete: $metadata->onDelete,
            onUpdate: $metadata->onUpdate,
        );
    }

    /**
     * Build an Index schema from IndexMetadata.
     *
     * A declared name is never shortened: one over 63 bytes is rejected, since PostgreSQL would silently truncate
     * it and MySQL would refuse it.
     *
     * @param class-string $entityClass The entity that declares the index, named in the error
     * @param string $tableName The table the index belongs to, named in the error
     * @throws EntityException When the declared name is longer than 63 bytes
     */
    public function buildIndex(
        IndexMetadata $metadata,
        string $entityClass,
        string $tableName,
    ): Index {
        if (!IdentifierName::fits($metadata->name)) {
            throw EntityException::indexNameTooLong(
                entityClass: $entityClass,
                tableName: $tableName,
                indexName: $metadata->name,
                byteLength: IdentifierName::byteLength($metadata->name),
            );
        }

        return new Index(
            name: $metadata->name,
            columns: $metadata->columns,
            type: $metadata->unique ? IndexType::Unique : IndexType::Btree,
            where: $metadata->where,
        );
    }

    /**
     * Build ForeignKey schemas from columns with references, using an explicit table name.
     * Used when merging extender columns under the parent's table name.
     *
     * @param array<ColumnMetadata> $columns
     * @return array<ForeignKey>
     */
    public function buildForeignKeysForTable(
        string $tableName,
        array $columns,
    ): array {
        return $this->buildForeignKeys($tableName, $columns);
    }

    /**
     * Build ForeignKey schemas from columns with references.
     *
     * @param string $tableName The table name (for generating FK names)
     * @param array<ColumnMetadata> $columns
     * @return array<ForeignKey>
     */
    private function buildForeignKeys(
        string $tableName,
        array $columns,
    ): array {
        $foreignKeys = [];

        foreach ($columns as $column) {
            if ($column->references === null) {
                continue;
            }

            // Parse references format: "table.column"
            $parts = explode('.', $column->references);
            if (count($parts) !== 2) {
                continue;
            }

            [$referencedTable, $referencedColumn] = $parts;

            // FK name: fk_{table}_{column}, shortened to 63 bytes when longer
            $fkName = IdentifierName::derive("{$tableName}_$column->name", prefix: 'fk_');

            $foreignKeys[] = new ForeignKey(
                name: $fkName,
                columns: [$column->name],
                referencedTable: $referencedTable,
                referencedColumns: [$referencedColumn],
                onDelete: $column->onDelete,
                onUpdate: $column->onUpdate,
            );
        }

        return $foreignKeys;
    }
}
