<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds metadata about a single entity property.
 */
readonly class PropertyMetadata
{
    /**
     * @param ?class-string $castClass Custom cast declared with #[Cast], or null for the built-in conversion
     * @param bool $encrypted Whether the column is declared #[Encrypted]
     * @param bool $isGenerated Whether the database generates the primary key (#[Column(generated: true)])
     */
    public function __construct(
        public string $name,
        public string $columnName,
        public string $type,
        public bool $nullable = false,
        public bool $isPrimaryKey = false,
        public bool $isAutoIncrement = false,
        public ?string $enumClass = null,
        public mixed $default = null,
        public ?string $columnType = null,
        public ?string $castClass = null,
        public bool $encrypted = false,
        public bool $isGenerated = false,
    ) {}
}
