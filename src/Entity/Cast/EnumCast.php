<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use BackedEnum;
use Marko\Database\Entity\PropertyMetadata;

/**
 * Built-in cast for BackedEnum properties, stored as their backing value.
 */
class EnumCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $meta->enumClass;

        if ($value instanceof $enumClass) {
            return $value;
        }

        return $enumClass::from($value);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
