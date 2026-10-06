<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function timezoneTestDatabaseArray(
    array $extra = [],
): array {
    return [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'app',
        'username' => 'root',
        'password' => '',
        ...$extra,
    ];
}

/**
 * Build a DatabaseConfig through the constructor, from a temporary config/database.php.
 *
 * @param array<string, mixed> $config
 */
function databaseConfigFromFile(
    array $config,
): DatabaseConfig {
    $tempDir = sys_get_temp_dir() . '/marko_dbtz_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/config', recursive: true);
    file_put_contents($tempDir . '/config/database.php', '<?php return ' . var_export($config, true) . ';');

    try {
        return new DatabaseConfig(new ProjectPaths($tempDir));
    } finally {
        unlink($tempDir . '/config/database.php');
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }
}

describe('DatabaseConfig timezone', function (): void {
    it('defaults the timezone to UTC when config/database.php has no timezone key', function (): void {
        $config = databaseConfigFromFile(timezoneTestDatabaseArray());

        expect($config->timezone->getName())->toBe('UTC');
    });

    it('reads the timezone from config/database.php', function (): void {
        $config = databaseConfigFromFile(timezoneTestDatabaseArray(['timezone' => 'America/New_York']));

        expect($config->timezone->getName())->toBe('America/New_York');
    });

    it('reads the timezone in fromArray()', function (): void {
        expect(
            DatabaseConfig::fromArray(timezoneTestDatabaseArray(['timezone' => 'Europe/Paris']))->timezone->getName(),
        )
            ->toBe('Europe/Paris')
            ->and(DatabaseConfig::fromArray(timezoneTestDatabaseArray())->timezone->getName())
            ->toBe('UTC');
    });

    it('rejects an invalid timezone with ConfigurationException', function (): void {
        expect(fn () => DatabaseConfig::fromArray(timezoneTestDatabaseArray(['timezone' => 'Mars/Olympus'])))
            ->toThrow(ConfigurationException::class, 'Invalid database timezone: Mars/Olympus')
            ->and(fn () => databaseConfigFromFile(timezoneTestDatabaseArray(['timezone' => ''])))
            ->toThrow(ConfigurationException::class, 'Invalid database timezone');
    });

    it(
        'reports a fixed offset for UTC, numeric offsets and abbreviations',
        function (string $timezone, string $offset): void {
            $config = DatabaseConfig::fromArray(timezoneTestDatabaseArray(['timezone' => $timezone]));

            expect($config->fixedTimezoneOffset())->toBe($offset);
        },
    )->with([
        'UTC' => ['UTC', '+00:00'],
        'lowercase utc' => ['utc', '+00:00'],
        'positive offset' => ['+05:30', '+05:30'],
        'negative offset' => ['-03:00', '-03:00'],
        'abbreviation' => ['CEST', '+02:00'],
    ]);

    it('reports no fixed offset for a region zone', function (): void {
        $config = DatabaseConfig::fromArray(timezoneTestDatabaseArray(['timezone' => 'America/New_York']));

        expect($config->fixedTimezoneOffset())->toBeNull();
    });
});
