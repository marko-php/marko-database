<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class Index
{
    /**
     * @param array<string> $columns
     * @param string|null $where SQL predicate that makes this a partial index (PostgreSQL only)
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
        public ?string $where = null,
    ) {}
}
