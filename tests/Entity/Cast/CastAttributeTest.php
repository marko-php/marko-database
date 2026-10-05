<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use Marko\Database\Attributes\Cast;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Cast\CastInterface;
use Marko\Database\Entity\Cast\EquatableCastInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Repository\Repository;
use Marko\Database\Tests\Entity\Cast\Fixtures\RecordingSqliteConnection;

readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}
}

class MoneyCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        [$cents, $currency] = explode(':', (string) $value);

        return new Money((int) $cents, $currency);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $value->cents . ':' . $value->currency;
    }
}

class MutableBox
{
    public function __construct(
        public string $label,
    ) {}
}

class MutableBoxCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return new MutableBox((string) $value);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $value->label;
    }
}

class CaseInsensitiveBoxCast extends MutableBoxCast implements EquatableCastInterface
{
    public static int $equalsCalls = 0;

    public function equals(
        mixed $a,
        mixed $b,
        PropertyMetadata $meta,
    ): bool {
        self::$equalsCalls++;

        return strtolower($a->label) === strtolower($b->label);
    }
}

class NotACast {}

#[Table('cast_things')]
class CastThing extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(type: 'varchar')]
    #[Cast(MoneyCast::class)]
    public ?Money $price = null;

    #[Column(type: 'varchar')]
    #[Cast(MutableBoxCast::class)]
    public ?MutableBox $box = null;

    #[Column(type: 'varchar', name: 'loose_box')]
    #[Cast(CaseInsensitiveBoxCast::class)]
    public ?MutableBox $looseBox = null;

    #[Column(type: 'json')]
    #[Cast(JsonMoneyCast::class)]
    public ?Money $jsonPrice = null;
}

class JsonMoneyCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        $data = json_decode((string) $value, true);

        return new Money($data['cents'], $data['currency']);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return json_encode(['cents' => $value->cents, 'currency' => $value->currency]);
    }
}

#[Table('cast_things')]
class BadCastEntity extends Entity
{
    #[Column(primaryKey: true)]
    public int $id = 0;

    #[Column]
    #[Cast(NotACast::class)]
    public string $name = '';
}

#[Table(extends: CastThing::class)]
class CastThingExtension extends Entity
{
    #[Column(type: 'varchar', name: 'ext_price')]
    #[Cast(MoneyCast::class)]
    public ?Money $extPrice = null;
}

class CastThingRepository extends Repository
{
    protected const string ENTITY_CLASS = CastThing::class;
}

function castThingConnection(): RecordingSqliteConnection
{
    $connection = new RecordingSqliteConnection();
    $connection->createTable(
        'CREATE TABLE cast_things (id INTEGER PRIMARY KEY AUTOINCREMENT, price TEXT, box TEXT, '
        . 'loose_box TEXT, json_price TEXT, ext_price TEXT)',
    );

    return $connection;
}

function castThingRepository(
    RecordingSqliteConnection $connection,
): CastThingRepository {
    return new CastThingRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
}

it('reads the cast class into property metadata', function (): void {
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);

    expect($metadata->getProperty('price')->castClass)->toBe(MoneyCast::class)
        ->and($metadata->getProperty('id')->castClass)->toBeNull();
});

it('throws when the cast class does not implement CastInterface', function (): void {
    new EntityMetadataFactory()->parse(BadCastEntity::class);
})->throws(EntityException::class, 'must implement Marko\Database\Entity\Cast\CastInterface');

it('round-trips a custom cast through hydrate, insert, update and hydrate', function (): void {
    $connection = castThingConnection();
    $repository = castThingRepository($connection);

    $thing = new CastThing();
    $thing->price = new Money(1250, 'USD');
    $repository->save($thing);

    expect($connection->lastInsertValues()['price'])->toBe('1250:USD');

    $thing->price = new Money(999, 'EUR');
    $repository->save($thing);

    expect($connection->lastUpdateValues()['price'])->toBe('999:EUR');

    $loaded = $repository->find($thing->id);

    expect($loaded->price)->toBeInstanceOf(Money::class)
        ->and($loaded->price->cents)->toBe(999)
        ->and($loaded->price->currency)->toBe('EUR');
});

it('does not mark an unchanged value-object property dirty', function (): void {
    $repository = castThingRepository(castThingConnection());
    $thing = new CastThing();
    $thing->price = new Money(1250, 'USD');
    $repository->save($thing);

    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);
    $loaded = $hydrator->hydrate(CastThing::class, ['id' => 1, 'price' => '1250:USD'], $metadata);
    $loaded->price = new Money(1250, 'USD');

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBeEmpty();
});

it('marks a value-object property dirty when its database representation changes', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);
    $loaded = $hydrator->hydrate(CastThing::class, ['id' => 1, 'price' => '1250:USD'], $metadata);
    $loaded->price = new Money(1300, 'USD');

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBe(['price']);
});

it('uses the cast equals hook when the cast implements EquatableCastInterface', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);
    $loaded = $hydrator->hydrate(CastThing::class, ['id' => 1, 'loose_box' => 'Hello'], $metadata);
    CaseInsensitiveBoxCast::$equalsCalls = 0;

    $loaded->looseBox = new MutableBox('HELLO');

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBeEmpty()
        ->and(CaseInsensitiveBoxCast::$equalsCalls)->toBeGreaterThan(0);

    $loaded->looseBox = new MutableBox('other');

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBe(['looseBox']);
});

it('detects an in-place mutation of a mutable value object as dirty', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);
    $loaded = $hydrator->hydrate(CastThing::class, ['id' => 1, 'box' => 'first'], $metadata);

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBeEmpty();

    $loaded->box->label = 'second';

    expect($hydrator->getDirtyProperties($loaded, $metadata))->toBe(['box']);
});

it('keeps getOriginalValues returning PHP values for cast properties', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = new EntityMetadataFactory()->parse(CastThing::class);
    $loaded = $hydrator->hydrate(CastThing::class, ['id' => 1, 'price' => '1250:USD'], $metadata);

    expect($hydrator->getOriginalValues($loaded)['price'])->toBeInstanceOf(Money::class);
});

it('applies casts to extender companion properties', function (): void {
    $factory = new EntityMetadataFactory();
    $metadata = $factory->linkExtenders(CastThing::class, [CastThingExtension::class]);
    $hydrator = new EntityHydrator($factory);

    $thing = $hydrator->hydrate(CastThing::class, ['id' => 1, 'ext_price' => '500:GBP'], $metadata);
    $companion = $thing->companion(CastThingExtension::class);

    expect($companion->extPrice)->toBeInstanceOf(Money::class)
        ->and($companion->extPrice->currency)->toBe('GBP');

    $companion->extPrice = new Money(700, 'GBP');
    $companionMetadata = $factory->parse(CastThingExtension::class);

    expect($hydrator->getDirtyProperties($companion, $companionMetadata))->toBe(['extPrice'])
        ->and($hydrator->extractAll($thing, $metadata)['ext_price'])->toBe('700:GBP');
});

it('allows a json column type on a cast property with a non-array PHP type', function (): void {
    $connection = castThingConnection();
    $repository = castThingRepository($connection);

    $thing = new CastThing();
    $thing->jsonPrice = new Money(42, 'USD');
    $repository->save($thing);

    $loaded = $repository->find($thing->id);

    expect($connection->lastInsertValues()['json_price'])->toBe('{"cents":42,"currency":"USD"}')
        ->and($loaded->jsonPrice->cents)->toBe(42);
});

it('converts query criteria for cast properties through the pipeline', function (): void {
    $connection = castThingConnection();
    $repository = castThingRepository($connection);

    $thing = new CastThing();
    $thing->price = new Money(1250, 'USD');
    $repository->save($thing);

    $criteria = ['price' => new Money(1250, 'USD')];

    expect($repository->findBy($criteria))->toHaveCount(1)
        ->and($repository->findOneBy($criteria)->id)->toBe($thing->id)
        ->and($repository->existsBy($criteria))->toBeTrue()
        ->and($repository->existsBy(['price' => new Money(1, 'USD')]))->toBeFalse();
});
