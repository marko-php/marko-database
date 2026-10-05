<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use Marko\Database\Entity\PropertyMetadata;

/**
 * Built-in cast for int, float, bool and string properties.
 *
 * Database drivers may return numbers as strings and booleans as 0/1, so values are
 * coerced to the declared PHP type on read. Writes pass through unchanged.
 */
class ScalarCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return match ($meta->type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $value;
    }
}
