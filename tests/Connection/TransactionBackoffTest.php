<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Exceptions\DeadlockException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;
use Marko\Testing\Fake\FakeSleeper;
use Random\Engine\Mt19937;
use Random\Randomizer;

function backoffConflict(): DeadlockException
{
    return DeadlockException::fromDriverError(
        new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock'),
        'UPDATE items SET name = ?',
        [],
    );
}

describe('TransactionBackoff', function (): void {
    it('waits a random delay between zero and the base delay after the first failed attempt', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper, new Randomizer(new Mt19937(42)));
        $expected = new Randomizer(new Mt19937(42))->getInt(0, 10);

        $backoff->wait(1, null, backoffConflict());

        expect($sleeper->sleeps)->toBe([$expected]);
        expect($sleeper->sleeps[0])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });

    it('doubles the upper bound of the default delay with each failed attempt', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper, new Randomizer(new Mt19937(7)));
        $reference = new Randomizer(new Mt19937(7));
        $expected = [];

        foreach ([1 => 10, 2 => 20, 3 => 40, 4 => 80, 5 => 160, 6 => 320] as $attempt => $bound) {
            $backoff->wait($attempt, null, backoffConflict());
            $expected[] = $reference->getInt(0, $bound);
        }

        expect($sleeper->sleeps)->toBe($expected);
    });

    it('caps the default delay at 500 milliseconds', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper, new Randomizer(new Mt19937(3)));
        $reference = new Randomizer(new Mt19937(3));
        $expected = [];

        foreach ([7, 8, 20, 64, PHP_INT_MAX] as $attempt) {
            $backoff->wait($attempt, null, backoffConflict());
            $expected[] = $reference->getInt(0, 500);
        }

        expect($sleeper->sleeps)->toBe($expected);
        expect(max($sleeper->sleeps))->toBeLessThanOrEqual(TransactionBackoff::MAX_DELAY_MILLISECONDS);
    });

    it('waits a fixed number of milliseconds when backoff is an int', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper);

        $backoff->wait(1, 25, backoffConflict());
        $backoff->wait(2, 25, backoffConflict());
        $backoff->wait(3, 0, backoffConflict());

        expect($sleeper->sleeps)->toBe([25, 25, 0]);
    });

    it('passes the failed attempt number and the conflict to a closure backoff', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper);
        $conflict = backoffConflict();
        $received = [];

        $custom = function (int $attempt, TransactionConflictException $e) use (&$received): int {
            $received[] = [$attempt, $e];

            return $attempt * 100;
        };

        $backoff->wait(1, $custom, $conflict);
        $backoff->wait(2, $custom, $conflict);

        expect($sleeper->sleeps)->toBe([100, 200]);
        expect($received)->toBe([[1, $conflict], [2, $conflict]]);
    });

    it('rejects a negative int backoff', function (): void {
        $backoff = new TransactionBackoff(new FakeSleeper());

        expect(fn () => $backoff->validate(-1))
            ->toThrow(TransactionException::class, 'A transaction backoff cannot be negative, -1 milliseconds given');
    });

    it('accepts null, zero, a positive int and a closure backoff', function (): void {
        $backoff = new TransactionBackoff(new FakeSleeper());

        $backoff->validate(null);
        $backoff->validate(0);
        $backoff->validate(50);
        $backoff->validate(fn (int $attempt): int => $attempt);

        expect(true)->toBeTrue();
    });

    it('rejects a closure backoff that returns a negative delay', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper);

        expect(fn () => $backoff->wait(1, fn (): int => -5, backoffConflict()))
            ->toThrow(TransactionException::class, 'The transaction backoff closure returned -5 milliseconds');

        $sleeper->assertNotSlept();
    });

    it('rejects a closure backoff that returns a non-integer', function (): void {
        $sleeper = new FakeSleeper();
        $backoff = new TransactionBackoff($sleeper);

        expect(fn () => $backoff->wait(1, fn (): string => '10', backoffConflict()))
            ->toThrow(TransactionException::class, 'The transaction backoff closure returned a string');

        $sleeper->assertNotSlept();
    });

    it('sleeps through the real sleeper by default', function (): void {
        $backoff = new TransactionBackoff();
        $started = hrtime(true);

        $backoff->wait(1, 20, backoffConflict());

        expect((hrtime(true) - $started) / 1_000_000)->toBeGreaterThanOrEqual(19.0);
    });
});
