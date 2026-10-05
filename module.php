<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Command\ConfirmationPrompterInterface;
use Marko\Database\Command\StdinConfirmationPrompter;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Seed\SeederDiscovery;
use Marko\Database\Seed\SeederDiscoveryInterface;
use Marko\Database\Seed\SeederRunner;

return [
    'singletons' => [
        EntityMetadataFactory::class,
        // Shared so the dirty-check snapshot taken when one repository loads an
        // entity is still there when another repository or service saves it.
        EntityHydrator::class,
    ],
    'boot' => function (
        EntityDiscovery $discovery,
        EntityMetadataFactory $metadataFactory,
        ProjectPaths $paths,
    ): void {
        $entityClasses = array_merge(
            $discovery->discoverInVendor($paths->vendor),
            $discovery->discoverInModules($paths->modules),
            $discovery->discoverInApp($paths->app),
        );
        $metadataFactory->linkExtendersFrom($entityClasses);
    },
    'bindings' => [
        SeederDiscoveryInterface::class => SeederDiscovery::class,
        ConfirmationPrompterInterface::class => StdinConfirmationPrompter::class,
        DiffCalculator::class => function (ContainerInterface $container): DiffCalculator {
            return new DiffCalculator(
                ignoredIndexes: $container->get(DatabaseConfig::class)->ignoreIndexes,
            );
        },
        SeederRunner::class => function (ContainerInterface $container): SeederRunner {
            $discovery = $container->get(SeederDiscoveryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            // Discover all seeder definitions
            $definitions = array_merge(
                $discovery->discoverInVendor($paths->vendor),
                $discovery->discoverInModules($paths->modules),
                $discovery->discoverInApp($paths->app),
            );

            // Instantiate each seeder via container (for DI)
            $seeders = [];
            foreach ($definitions as $definition) {
                $seeders[$definition->seederClass] = $container->get($definition->seederClass);
            }

            // Get transaction manager if available (requires a database driver)
            $transaction = $container->has(TransactionInterface::class)
                ? $container->get(TransactionInterface::class)
                : null;

            return new SeederRunner(
                seeders: $seeders,
                appEnvironment: $container->get(AppEnvironment::class),
                transaction: $transaction,
            );
        },
    ],
];
