<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

use InvalidArgumentException;

/**
 * The length rule for index and constraint names.
 *
 * PostgreSQL truncates identifiers to 63 bytes and MySQL rejects names over 64 characters, so 63 bytes fits both.
 * A name Marko derives from a table and column is shortened to fit; a name the developer declares is not.
 */
class IdentifierName
{
    public const int MAX_BYTES = 63;

    private const int HASH_BYTES = 8;

    /**
     * The name `<prefix><body><suffix>`, unchanged when it fits in 63 bytes.
     *
     * A longer name keeps its prefix and suffix, cuts the body, and adds the crc32b hash of the full name:
     * `<prefix><cut body>_<8 hex><suffix>`. The result is the same on every run and every driver, and two long names
     * that share a beginning still differ.
     *
     * @throws InvalidArgumentException When the prefix and suffix leave no room for the body and the hash
     */
    public static function derive(
        string $body,
        string $prefix = '',
        string $suffix = '',
    ): string {
        $name = $prefix . $body . $suffix;

        if (self::fits($name)) {
            return $name;
        }

        $room = self::MAX_BYTES - strlen($prefix) - strlen($suffix) - self::HASH_BYTES - 1;

        if ($room < 1) {
            throw new InvalidArgumentException(
                "Prefix '$prefix' and suffix '$suffix' leave no room for the body of a shortened "
                . self::MAX_BYTES . '-byte identifier',
            );
        }

        $cut = rtrim(mb_strcut($body, 0, $room, 'UTF-8'), '_');

        return $prefix . $cut . '_' . hash('crc32b', $name) . $suffix;
    }

    /**
     * Whether a name fits in 63 bytes.
     */
    public static function fits(
        string $name,
    ): bool {
        return self::byteLength($name) <= self::MAX_BYTES;
    }

    /**
     * The length of a name in bytes, the unit PostgreSQL limits.
     */
    public static function byteLength(
        string $name,
    ): int {
        return strlen($name);
    }
}
