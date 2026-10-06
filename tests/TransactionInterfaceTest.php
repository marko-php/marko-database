<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionInterface;

describe('TransactionInterface', function (): void {
    it('defines TransactionInterface with begin, commit, and rollback methods', function (): void {
        $reflection = new ReflectionClass(TransactionInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('beginTransaction'))->toBeTrue()
            ->and($reflection->hasMethod('commit'))->toBeTrue()
            ->and($reflection->hasMethod('rollback'))->toBeTrue()
            ->and($reflection->getMethod('beginTransaction')->getReturnType()?->getName())->toBe('void')
            ->and($reflection->getMethod('commit')->getReturnType()?->getName())->toBe('void')
            ->and($reflection->getMethod('rollback')->getReturnType()?->getName())->toBe('void');
    });

    it('defines TransactionInterface with transaction callback method', function (): void {
        $reflection = new ReflectionClass(TransactionInterface::class);
        $transaction = $reflection->getMethod('transaction');
        $params = $transaction->getParameters();

        expect($reflection->hasMethod('transaction'))->toBeTrue()
            ->and($transaction->getReturnType()?->getName())->toBe('mixed')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('callback')
            ->and($params[0]->getType()?->getName())->toBe('callable');
    });

    it('declares an attempts parameter defaulting to one on TransactionInterface::transaction()', function (): void {
        $attempts = new ReflectionMethod(TransactionInterface::class, 'transaction')->getParameters()[1];

        expect($attempts->getName())->toBe('attempts')
            ->and($attempts->getType()?->getName())->toBe('int')
            ->and($attempts->isOptional())->toBeTrue()
            ->and($attempts->getDefaultValue())->toBe(1);
    });

    it('defines transactionLevel returning int', function (): void {
        $method = new ReflectionMethod(TransactionInterface::class, 'transactionLevel');

        expect($method->getReturnType()?->getName())->toBe('int')
            ->and($method->getParameters())->toBeEmpty();
    });

    it('defines afterCommit and afterRollback taking a callable', function (string $name): void {
        $method = new ReflectionMethod(TransactionInterface::class, $name);
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('void')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getType()?->getName())->toBe('callable');
    })->with(['afterCommit', 'afterRollback']);
});
