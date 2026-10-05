<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use DateTimeZone;
use Exception;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;
use ReflectionClass;
use ReflectionProperty;

/**
 * The timezone entity datetimes are stored in, read from the optional `timezone`
 * key of config/database.php. Defaults to UTC when the key is absent.
 *
 * Kept separate from DatabaseConfig because it applies to every driver, including
 * the read/write split driver whose config/database.php has no host settings.
 */
readonly class DatabaseTimezoneConfig
{
    public const string DEFAULT_TIMEZONE = 'UTC';

    public DateTimeZone $timezone;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        ProjectPaths $paths,
    ) {
        $configPath = $paths->config . '/database.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $name = is_array($config) ? ($config['timezone'] ?? self::DEFAULT_TIMEZONE) : self::DEFAULT_TIMEZONE;

        $this->timezone = self::parse($name);
    }

    /**
     * Build the config for an explicit timezone name, without reading config files.
     *
     * @throws ConfigurationException
     */
    public static function fromName(
        string $name,
    ): self {
        $instance = new ReflectionClass(self::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(self::class, 'timezone')->setValue($instance, self::parse($name));

        return $instance;
    }

    /**
     * @throws ConfigurationException
     */
    private static function parse(
        mixed $name,
    ): DateTimeZone {
        if (!is_string($name) || $name === '') {
            throw ConfigurationException::invalidTimezone(var_export($name, true));
        }

        try {
            return new DateTimeZone($name);
        } catch (Exception) {
            throw ConfigurationException::invalidTimezone($name);
        }
    }
}
