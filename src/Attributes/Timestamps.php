<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

/**
 * Maintains created/updated timestamp properties automatically on insert and update.
 *
 * Pass null for either argument to disable that timestamp.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Timestamps
{
    public function __construct(
        public ?string $createdAt = 'createdAt',
        public ?string $updatedAt = 'updatedAt',
    ) {}
}
