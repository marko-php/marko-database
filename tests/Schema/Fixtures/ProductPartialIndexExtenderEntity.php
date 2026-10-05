<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: ProductEntity::class, unmanagedIndexes: ['products_search_gin_idx'])]
#[Index(name: 'idx_products_active_sku', columns: ['sku'], where: 'sku IS NOT NULL')]
class ProductPartialIndexExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public ?string $sku = null;
}
