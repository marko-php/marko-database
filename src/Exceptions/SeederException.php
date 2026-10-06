<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for seeder-related errors.
 */
class SeederException extends MarkoException
{
    public static function blockedInProduction(): self
    {
        return new self(
            message: 'Seeders cannot be run in production environment',
            context: 'Attempting to run seeders',
            suggestion: 'Seeders are never run in production, even with --force. Set MARKO_ENV or APP_ENV to a development or testing name to run seeders.',
        );
    }

    public static function requiresForce(
        string $environment,
    ): self {
        return new self(
            message: "Seeders are refused in the '$environment' environment without force",
            context: 'Attempting to run seeders',
            suggestion: 'Seeders run freely only in development (development, dev, local) and testing (testing, test). '
                . 'Pass --force to db:seed, or force: true to SeederRunner, if this database may be seeded.',
        );
    }

    public static function seederNotFound(
        string $name,
    ): self {
        return new self(
            message: "Seeder '$name' not found",
            context: "While looking up seeder '$name'",
            suggestion: 'Ensure the seeder class has the #[Seeder] attribute with the correct name.',
        );
    }
}
