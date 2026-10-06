<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Database\Exceptions\DeadlockException;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\SerializationFailureException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;

function concurrencyPdoError(
    string $sqlState,
    int $driverCode,
    string $driverMessage,
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: General error: $driverCode $driverMessage");
    $exception->errorInfo = [$sqlState, $driverCode, $driverMessage];

    return $exception;
}

describe('concurrency exceptions', function (): void {
    it(
        'builds a DeadlockException from a driver error with the SQLSTATE, SQL and redacted message',
        function (): void {
            $pdoException = concurrencyPdoError(
                '40P01',
                7,
                "ERROR:  deadlock detected\nDETAIL:  Process 12 waits for ShareLock on transaction 99; blocked by process 13.",
            );

            $exception = DeadlockException::fromDriverError(
                $pdoException,
                'UPDATE accounts SET balance = ? WHERE id = ?',
                [10, 2],
            );

            expect($exception)->toBeInstanceOf(DeadlockException::class)
                ->and($exception)->toBeInstanceOf(QueryException::class)
                ->and($exception->sqlState())->toBe('40P01')
                ->and($exception->sql())->toBe('UPDATE accounts SET balance = ? WHERE id = ?')
                ->and($exception->bindings())->toBe([10, 2])
                ->and($exception->getPrevious())->toBe($pdoException)
                ->and($exception->getMessage())->toContain('deadlock detected')
                ->and($exception->getMessage())->not->toContain('Process 12')
                ->and($exception->getContext())->toContain('UPDATE accounts')
                ->and($exception->getSuggestion())->toContain('attempts');
        },
    );

    it('builds a SerializationFailureException from a driver error', function (): void {
        $pdoException = concurrencyPdoError(
            '40001',
            7,
            'ERROR:  could not serialize access due to concurrent update',
        );

        $exception = SerializationFailureException::fromDriverError($pdoException, 'UPDATE items SET n = 1', []);

        expect($exception)->toBeInstanceOf(SerializationFailureException::class)
            ->and($exception->sqlState())->toBe('40001')
            ->and($exception->getPrevious())->toBe($pdoException)
            ->and($exception->getMessage())->toContain('could not serialize access')
            ->and($exception->getSuggestion())->toContain('attempts');
    });

    it('builds a LockTimeoutException from a driver error', function (): void {
        $pdoException = concurrencyPdoError(
            '55P03',
            7,
            'ERROR:  could not obtain lock on row in relation "jobs"',
        );

        $exception = LockTimeoutException::fromDriverError($pdoException, 'SELECT * FROM jobs FOR UPDATE NOWAIT', []);

        expect($exception)->toBeInstanceOf(LockTimeoutException::class)
            ->and($exception)->toBeInstanceOf(QueryException::class)
            ->and($exception->sqlState())->toBe('55P03')
            ->and($exception->getPrevious())->toBe($pdoException)
            ->and($exception->getMessage())->toContain('could not obtain lock')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it('marks deadlocks and serialization failures as retryable transaction conflicts', function (): void {
        $deadlock = DeadlockException::fromDriverError(
            concurrencyPdoError('40001', 1213, 'Deadlock found'),
            'UPDATE t SET a = 1',
            [],
        );
        $serialization = SerializationFailureException::fromDriverError(
            concurrencyPdoError('40001', 7, 'ERROR:  could not serialize access'),
            'UPDATE t SET a = 1',
            [],
        );

        expect($deadlock)->toBeInstanceOf(TransactionConflictException::class)
            ->and($deadlock->isRetryable())->toBeTrue()
            ->and($serialization)->toBeInstanceOf(TransactionConflictException::class)
            ->and($serialization->isRetryable())->toBeTrue()
            ->and(new ReflectionClass(TransactionConflictException::class)->getParentClass()->getName())
            ->toBe(QueryException::class);
    });

    it('does not make LockTimeoutException a TransactionConflictException', function (): void {
        $exception = LockTimeoutException::fromDriverError(
            concurrencyPdoError('HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'),
            'UPDATE t SET a = 1',
            [],
        );

        expect($exception)->not->toBeInstanceOf(TransactionConflictException::class);
    });

    it('does not map the concurrency exceptions to an HTTP status', function (): void {
        foreach ([DeadlockException::class, SerializationFailureException::class, LockTimeoutException::class] as $class) {
            expect(is_subclass_of($class, HttpExceptionInterface::class))->toBeFalse();
        }
    });

    it('never copies bound values into the message', function (): void {
        $pdoException = concurrencyPdoError(
            '40P01',
            7,
            'ERROR:  deadlock detected while updating "secret-account-name"',
        );

        $exception = DeadlockException::fromDriverError(
            $pdoException,
            'UPDATE accounts SET owner = ? WHERE name = ?',
            ['alice@example.com', 'secret-account-name'],
        );

        expect($exception->getMessage())->not->toContain('secret-account-name')
            ->and($exception->getMessage())->toContain('[redacted]')
            ->and($exception->getContext())->not->toContain('alice@example.com');
    });

    it('explains an attempts value below one', function (): void {
        $exception = TransactionException::invalidAttempts(0);

        expect($exception->getMessage())->toContain('0')
            ->and($exception->getMessage())->toContain('attempts')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });
});
