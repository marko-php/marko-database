<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

/**
 * Maps an entity property to a table column.
 *
 * Set generated: true on a primary key whose value the database produces
 * through its default (e.g. gen_random_uuid()): the repository leaves the
 * key out of the INSERT and reads it back with INSERT ... RETURNING.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?int $length = null,
        public ?string $type = null,
        public bool $unique = false,
        public mixed $default = null,
        public ?string $references = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?bool $nullable = null,
        public bool $generated = false,
    ) {}
}
