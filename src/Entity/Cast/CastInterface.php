<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use Marko\Database\Entity\PropertyMetadata;

/**
 * Converts a single entity property between its PHP value and its database value.
 *
 * Casts never receive null: SQL NULL always hydrates to PHP null and vice versa.
 * Casts are resolved through the container, so they may declare constructor
 * dependencies and can be replaced with a Preference.
 */
interface CastInterface
{
    /**
     * Convert a non-null database value to the PHP value assigned to the property.
     */
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed;

    /**
     * Convert a non-null PHP property value to a value the database driver can bind.
     */
    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed;
}
