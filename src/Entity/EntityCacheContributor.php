<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;

/**
 * Stores the discovered entity classes in the discovery cache, so the
 * database boot callback links entity extenders without scanning vendor/,
 * modules/ and app/ on every request.
 */
readonly class EntityCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'entities';

    public function __construct(
        private EntityDiscovery $entityDiscovery,
        private ProjectPaths $projectPaths,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * Entity discovery scans the project paths (as the boot callback does), not the module list.
     *
     * @param array<ModuleManifest> $modules
     * @return array<int, class-string<Entity>>
     */
    public function compile(array $modules): array
    {
        return array_values($this->entityDiscovery->discoverAll(
            $this->projectPaths->vendor,
            $this->projectPaths->modules,
            $this->projectPaths->app,
        ));
    }
}
