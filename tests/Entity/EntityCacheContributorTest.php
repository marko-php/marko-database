<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Entity\EntityCacheContributor;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\BasicExtenderEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderParentEntity;

/**
 * @return array<string, mixed>
 */
function entityCacheModuleConfig(): array
{
    return require dirname(__DIR__, 2) . '/module.php';
}

describe('EntityCacheContributor', function (): void {
    it('compiles the discovered entity classes', function (): void {
        $id = bin2hex(random_bytes(6));
        $base = sys_get_temp_dir() . "/marko-entity-cache-$id";
        mkdir("$base/app/blog/src/Entity", 0755, true);
        file_put_contents("$base/app/blog/src/Entity/Post.php", <<<PHP
            <?php

            declare(strict_types=1);

            namespace EntityCache$id;

            use Marko\Database\Attributes\Column;
            use Marko\Database\Attributes\Table;
            use Marko\Database\Entity\Entity;

            #[Table('posts')]
            class Post extends Entity
            {
                #[Column(primaryKey: true)]
                public int \$id;
            }
            PHP);

        $contributor = new EntityCacheContributor(
            new EntityDiscovery(new ClassFileParser()),
            new ProjectPaths($base),
        );
        $section = $contributor->compile([]);

        unlink("$base/app/blog/src/Entity/Post.php");
        rmdir("$base/app/blog/src/Entity");
        rmdir("$base/app/blog/src");
        rmdir("$base/app/blog");
        rmdir("$base/app");
        rmdir($base);

        expect($contributor->key())->toBe('entities')
            ->and($section)->toBe(["EntityCache$id\\Post"]);
    });

    it('links extenders from the cached entity list without scanning', function (): void {
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        // An empty, missing project: a scan would find no entities at all.
        $container->instance(
            ProjectPaths::class,
            new ProjectPaths(sys_get_temp_dir() . '/marko-entity-cache-missing'),
        );
        $container->instance(CachedDiscovery::class, new CachedDiscovery([
            'entities' => [ExtenderParentEntity::class, BasicExtenderEntity::class],
        ]));
        $metadataFactory = new EntityMetadataFactory();
        $container->instance(EntityMetadataFactory::class, $metadataFactory);

        $container->call(entityCacheModuleConfig()['boot']);

        expect($metadataFactory->parse(ExtenderParentEntity::class)->extenders)->toBe([BasicExtenderEntity::class]);
    });

    it('declares the entity contributor in module.php', function (): void {
        expect(entityCacheModuleConfig()['discovery'])->toBe([EntityCacheContributor::class]);
    });
});
