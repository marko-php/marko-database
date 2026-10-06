<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;
use ReflectionClass;

/**
 * Database configuration loaded from config/database.php.
 */
readonly class DatabaseConfig
{
    public string $driver;

    public string $host;

    public int $port;

    public string $database;

    public string $username;

    public string $password;

    public ?string $sslMode;

    public ?string $sslRootCert;

    public bool $sslVerifyServerCert;

    public ?string $sslCert;

    public ?string $sslKey;

    /**
     * The zone stored datetimes are written in (the `timezone` key, UTC when absent). The connections pin the
     * database session to it on connect. The same key DatabaseTimezoneConfig reads.
     */
    public DateTimeZone $timezone;

    /**
     * Index names or fnmatch patterns that db:migrate never drops (migrations.ignore_indexes).
     *
     * @var list<string>
     */
    public array $ignoreIndexes;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        ProjectPaths $paths,
    ) {
        $configPath = $paths->config . '/database.php';

        if (!file_exists($configPath)) {
            throw ConfigurationException::configFileNotFound($configPath);
        }

        $config = require $configPath;

        self::validateConfigArray($config);

        $this->driver = $config['driver'];
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->sslMode = $config['sslmode'] ?? null;
        $this->sslRootCert = $config['ssl_ca'] ?? null;
        $this->sslVerifyServerCert = $config['ssl_verify_server_cert'] ?? ($config['ssl_ca'] ?? null) !== null;
        $this->sslCert = $config['ssl_cert'] ?? null;
        $this->sslKey = $config['ssl_key'] ?? null;
        $this->timezone = DatabaseTimezoneConfig::resolveTimezone(
            $config['timezone'] ?? DatabaseTimezoneConfig::DEFAULT_TIMEZONE,
        );
        $this->ignoreIndexes = array_values($config['migrations']['ignore_indexes'] ?? []);
    }

    /**
     * Create a DatabaseConfig from a raw configuration array.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    public static function fromArray(array $config): self
    {
        self::validateConfigArray($config);

        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();

        $props = [
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'sslMode' => $config['sslmode'] ?? null,
            'sslRootCert' => $config['ssl_ca'] ?? null,
            'sslVerifyServerCert' => $config['ssl_verify_server_cert'] ?? ($config['ssl_ca'] ?? null) !== null,
            'sslCert' => $config['ssl_cert'] ?? null,
            'sslKey' => $config['ssl_key'] ?? null,
            'timezone' => DatabaseTimezoneConfig::resolveTimezone(
                $config['timezone'] ?? DatabaseTimezoneConfig::DEFAULT_TIMEZONE,
            ),
            'ignoreIndexes' => array_values($config['migrations']['ignore_indexes'] ?? []),
        ];

        $reflection = new ReflectionClass($instance);

        foreach ($props as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * The timezone as a fixed UTC offset such as `+00:00` or `+05:30`, or null for a region zone such as
     * `America/New_York`, whose offset changes with daylight saving time and which the database has to
     * resolve by name. UTC, numeric offsets and abbreviations (`CEST`) have a fixed offset: PHP formats
     * them with that one offset, so the database session is given the same offset.
     */
    public function fixedTimezoneOffset(): ?string
    {
        if ($this->timezone->getName() !== 'UTC' && $this->timezone->getLocation() !== false) {
            return null;
        }

        return new DateTimeImmutable('now', $this->timezone)->format('P');
    }

    /**
     * Validate a database configuration array, throwing ConfigurationException on failure.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    private static function validateConfigArray(array $config): void
    {
        $requiredKeys = ['driver', 'host', 'port', 'database', 'username', 'password'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw ConfigurationException::missingRequiredKey($key);
            }
        }

        $sslCert = $config['ssl_cert'] ?? null;
        $sslKey = $config['ssl_key'] ?? null;

        if ($sslCert !== null && $sslKey === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_cert', 'ssl_key');
        }

        if ($sslKey !== null && $sslCert === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_key', 'ssl_cert');
        }

        $ignoreIndexes = $config['migrations']['ignore_indexes'] ?? [];

        $isListOfStrings = is_array($ignoreIndexes)
            && array_all($ignoreIndexes, static fn (mixed $name): bool => is_string($name));

        if (!$isListOfStrings) {
            throw ConfigurationException::invalidIgnoreIndexes();
        }
    }
}
