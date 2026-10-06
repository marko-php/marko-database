<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Declares an index name of 70 bytes, over the 63-byte identifier limit
#[Table(extends: ProductEntity::class)]
#[Index(name: 'idx_products_warehouse_location_code_and_supplier_reference_lookup_key', columns: ['location_code'])]
class ProductLongIndexExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $locationCode;
}
