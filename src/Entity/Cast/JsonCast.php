<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use JsonException;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\EntityException;

/**
 * Built-in cast for array properties stored in json columns.
 */
class JsonCast implements CastInterface
{
    /**
     * @throws EntityException
     */
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): array {
        try {
            return json_decode((string) $value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw EntityException::invalidJsonFromDatabase($value, $e->getMessage());
        }
    }

    /**
     * @throws EntityException
     */
    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): string {
        try {
            return json_encode($value, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw EntityException::invalidJsonEncode($e->getMessage());
        }
    }
}
