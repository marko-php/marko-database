<?php

declare(strict_types=1);

use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Expression;

describe('ExpressionDefaultMatcherInterface', function (): void {
    it('declares matchesStoredDefault on ExpressionDefaultMatcherInterface', function (): void {
        $reflection = new ReflectionClass(ExpressionDefaultMatcherInterface::class);
        $method = $reflection->getMethod('matchesStoredDefault');
        $parameters = $method->getParameters();

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->isSubclassOf(IntrospectorInterface::class))->toBeFalse()
            ->and($method->getReturnType()?->getName())->toBe('bool')
            ->and(array_map(fn (ReflectionParameter $parameter): string => $parameter->getName(), $parameters))
            ->toBe(['table', 'column', 'expression'])
            ->and($parameters[2]->getType()?->getName())->toBe(Expression::class);
    });
});
