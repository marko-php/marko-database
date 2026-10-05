<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Core\Container\Container;
use Marko\Database\Entity\Cast\CastInterface;
use Marko\Database\Entity\Cast\CastResolver;
use Marko\Database\Entity\Cast\DateTimeCast;
use Marko\Database\Entity\Cast\EnumCast;
use Marko\Database\Entity\Cast\JsonCast;
use Marko\Database\Entity\Cast\ScalarCast;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\EntityException;
use stdClass;

class PrefixCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return substr((string) $value, 4);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return 'pre:' . $value;
    }
}

class PrefixDependency
{
    public string $prefix = 'dep:';
}

readonly class DependentCast implements CastInterface
{
    public function __construct(
        public PrefixDependency $dependency,
    ) {}

    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $value;
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        return $this->dependency->prefix . $value;
    }
}

enum ResolverSuit: string
{
    case Hearts = 'H';
}

it('resolves cast classes through the container', function (): void {
    $resolver = new CastResolver(new Container());

    $cast = $resolver->resolve(DependentCast::class);

    expect($cast)->toBeInstanceOf(DependentCast::class)
        ->and($cast->toDatabase('x', new PropertyMetadata('p', 'p', 'string')))->toBe('dep:x');
});

it('instantiates no-argument casts without a container', function (): void {
    $resolver = new CastResolver();

    expect($resolver->resolve(PrefixCast::class))->toBeInstanceOf(PrefixCast::class);
});

it('throws a clear exception when a cast cannot be built without a container', function (): void {
    $resolver = new CastResolver();

    expect(fn () => $resolver->resolve(DependentCast::class))
        ->toThrow(EntityException::class, 'requires constructor dependencies');
});

it('throws when a class does not implement CastInterface', function (): void {
    $resolver = new CastResolver();

    expect(fn () => $resolver->resolve(stdClass::class))
        ->toThrow(EntityException::class, 'must implement');
});

it('caches resolved cast instances', function (): void {
    $resolver = new CastResolver(new Container());

    expect($resolver->resolve(DependentCast::class))->toBe($resolver->resolve(DependentCast::class));
});

it('converts json, enum, datetime and scalar values through built-in casts', function (): void {
    $json = new PropertyMetadata('data', 'data', 'array', columnType: 'json');
    $enum = new PropertyMetadata('suit', 'suit', ResolverSuit::class, enumClass: ResolverSuit::class);
    $date = new PropertyMetadata('at', 'at', DateTimeImmutable::class);
    $int = new PropertyMetadata('n', 'n', 'int');
    $bool = new PropertyMetadata('b', 'b', 'bool');

    $utc = new DateTimeZone('UTC');

    expect(new JsonCast()->toDatabase(['a' => 1], $json))->toBe('{"a":1}')
        ->and(new JsonCast()->toPhp('{"a":1}', $json))->toBe(['a' => 1])
        ->and(new EnumCast()->toDatabase(ResolverSuit::Hearts, $enum))->toBe('H')
        ->and(new EnumCast()->toPhp('H', $enum))->toBe(ResolverSuit::Hearts)
        ->and(new DateTimeCast()->toDatabase(new DateTimeImmutable('2026-01-02 03:04:05', $utc), $date))
        ->toBe('2026-01-02 03:04:05')
        ->and(new DateTimeCast()->toPhp('2026-01-02 03:04:05', $date)->format('Y-m-d H:i:s'))
        ->toBe('2026-01-02 03:04:05')
        ->and(new ScalarCast()->toPhp('42', $int))->toBe(42)
        ->and(new ScalarCast()->toPhp(0, $bool))->toBeFalse()
        ->and(new ScalarCast()->toDatabase(true, $bool))->toBeTrue();
});
