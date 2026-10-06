<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Index
{
    /**
     * @param array<string> $columns
     * @param string|null $where SQL predicate that makes this a partial index
     * @param bool $constraint Whether the index backs a UNIQUE constraint (PostgreSQL inline `UNIQUE`), which is
     *                         dropped with DROP CONSTRAINT rather than DROP INDEX. Set by introspection, ignored by
     *                         equals().
     */
    public function __construct(
        public string $name,
        public array $columns,
        public IndexType $type = IndexType::Btree,
        public ?string $where = null,
        public bool $constraint = false,
    ) {}

    public function equals(
        self $other,
    ): bool {
        return $this->name === $other->name
            && $this->columns === $other->columns
            && $this->type === $other->type
            && $this->where === $other->where;
    }
}
