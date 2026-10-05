<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Table
{
    /**
     * @param list<string> $unmanagedIndexes Index names (or fnmatch patterns) on this table that the entity diff
     *                                       never drops, for indexes created by hand in a migration
     */
    public function __construct(
        public ?string $name = null,
        public ?string $extends = null,
        public array $unmanagedIndexes = [],
    ) {}
}
