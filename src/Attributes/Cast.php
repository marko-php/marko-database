<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Cast
{
    public function __construct(
        public string $castClass,
    ) {}
}
