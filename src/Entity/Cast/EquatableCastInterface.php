<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use Marko\Database\Entity\PropertyMetadata;

/**
 * Optional hook for casts that decide themselves whether two PHP values are equal.
 *
 * Dirty checking calls equals() for non-null values that are not identical. Casts
 * without this hook are compared by their database representation.
 */
interface EquatableCastInterface extends CastInterface
{
    public function equals(
        mixed $a,
        mixed $b,
        PropertyMetadata $meta,
    ): bool;
}
