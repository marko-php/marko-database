<?php

declare(strict_types=1);

namespace Marko\Database\Config;

/**
 * Resolves whether the application is running in production.
 *
 * Used as the fallback for commands whose production flag the container cannot
 * supply, since it resolves builtin scalars to their default.
 */
final class Environment
{
    private const array PRODUCTION_VALUES = ['production', 'prod'];

    public static function isProduction(): bool
    {
        $value = $_ENV['MARKO_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('MARKO_ENV') ?: getenv('APP_ENV');

        // An unset environment counts as production: what this gates either
        // drops tables or writes migrations, so guessing wrong has to be safe.
        if (!is_string($value) || $value === '') {
            return true;
        }

        return in_array(strtolower($value), self::PRODUCTION_VALUES, true);
    }
}
