<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Thrown when an entity factory is misconfigured or misused.
 */
class EntityFactoryException extends MarkoException
{
    public static function noContainer(
        string $factoryClass,
    ): self {
        return new self(
            message: "Entity factory '$factoryClass' has no container, so it cannot resolve its repository",
            context: "Calling create() or createMany() on '$factoryClass'",
            suggestion: 'Construct the factory with the application container (new PostFactory($container)) or resolve it from the container; make() works without one',
        );
    }

    public static function invalidRepository(
        string $factoryClass,
        string $repository,
    ): self {
        $named = $repository === '' ? 'nothing' : "'$repository'";

        return new self(
            message: "Entity factory '$factoryClass' REPOSITORY constant does not name a repository (it names $named)",
            context: "Resolving the repository that create() persists through for '$factoryClass'",
            suggestion: 'Set protected const string REPOSITORY = PostRepository::class; to a class or interface implementing Marko\Database\Repository\RepositoryInterface',
        );
    }

    public static function invalidCount(
        string $factoryClass,
        int $count,
    ): self {
        return new self(
            message: "Entity factory '$factoryClass' cannot build $count entities",
            context: 'Calling makeMany() or createMany()',
            suggestion: 'Pass a count of zero or more',
        );
    }

    public static function emptySequence(
        string $factoryClass,
    ): self {
        return new self(
            message: "Entity factory '$factoryClass' sequence() needs at least one state",
            context: 'Calling sequence() without any state closures',
            suggestion: 'Pass one closure per value to cycle through, e.g. sequence(fn (Post $p) => $p->status = Status::Draft, fn (Post $p) => $p->status = Status::Live)',
        );
    }
}
