<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Command\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Schema\Expression;

#[Table('tokens')]
class ExpiringTokenEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(type: 'timestamp', default: new Expression("now() + interval '1 day'"))]
    public string $expiresAt;
}
