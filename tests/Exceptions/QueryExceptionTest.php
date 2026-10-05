<?php

declare(strict_types=1);

use Marko\Database\Exceptions\DatabaseException;
use Marko\Database\Exceptions\QueryException;

function queryExceptionPdoError(
    string $sqlState,
    int $driverCode,
    string $driverMessage,
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: General error: $driverCode $driverMessage");
    $exception->errorInfo = [$sqlState, $driverCode, $driverMessage];

    return $exception;
}

describe('QueryException', function (): void {
    it('carries the sql, bindings and previous PDOException', function (): void {
        $pdoException = queryExceptionPdoError('42P01', 7, 'ERROR:  relation "missing" does not exist');

        $exception = QueryException::fromDriverError($pdoException, 'SELECT * FROM missing WHERE id = ?', [5]);

        expect($exception)->toBeInstanceOf(DatabaseException::class)
            ->and($exception->sql())->toBe('SELECT * FROM missing WHERE id = ?')
            ->and($exception->bindings())->toBe([5])
            ->and($exception->sqlState())->toBe('42P01')
            ->and($exception->getPrevious())->toBe($pdoException)
            ->and($exception->getMessage())->toContain('relation "missing" does not exist')
            ->and($exception->getContext())->toContain('SELECT * FROM missing WHERE id = ?')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it('reads the SQLSTATE from the exception code when errorInfo is missing', function (): void {
        $pdoException = new PDOException('SQLSTATE[HY000]: General error');
        (new ReflectionProperty(Exception::class, 'code'))->setValue($pdoException, 'HY000');

        expect(QueryException::fromDriverError($pdoException, 'SELECT 1', [])->sqlState())->toBe('HY000');
    });

    it('redacts string bindings from the fallback message', function (): void {
        $pdoException = new PDOException(
            'SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: "s3cret-token"',
        );
        $pdoException->errorInfo = ['22P02', 7, 'ERROR:  invalid input syntax for type integer: "s3cret-token"'];

        $exception = QueryException::fromDriverError(
            $pdoException,
            'SELECT * FROM users WHERE id = ? AND meta = ?',
            ['s3cret-token', ['card' => '4111111111111111']],
        );

        expect($exception->getMessage())->not->toContain('s3cret-token')
            ->and($exception->getMessage())->toContain('[redacted]')
            ->and($exception->getContext())->not->toContain('s3cret-token')
            ->and($exception->getContext())->not->toContain('4111111111111111')
            ->and($exception->bindings())->toBe(['s3cret-token', ['card' => '4111111111111111']]);
    });

    it('does not corrupt the message when a short or numeric string binding appears in it', function (): void {
        $pdoException = queryExceptionPdoError('42P01', 7, 'ERROR:  relation "missing" does not exist');

        $exception = QueryException::fromDriverError($pdoException, 'SELECT * FROM missing WHERE a = ? AND b = ?', [
            '1',
            'a',
        ]);

        expect($exception->getMessage())->toContain('SQLSTATE[42P01]')
            ->and($exception->getMessage())->toContain('relation "missing" does not exist');
    });

    it('redacts short string bindings when the driver quotes them', function (): void {
        $pdoException = queryExceptionPdoError(
            '22P02',
            7,
            'ERROR:  invalid input syntax for type integer: "ab"',
        );

        $exception = QueryException::fromDriverError($pdoException, 'SELECT * FROM t WHERE id = ?', ['ab']);

        expect($exception->getMessage())->toContain('"[redacted]"')
            ->and($exception->getMessage())->not->toContain('"ab"');
    });

    it('drops driver DETAIL lines that carry row data from the message', function (): void {
        $pdoException = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 7 ERROR:  something failed\n"
            . 'DETAIL:  Failing row contains (1, private@example.com).',
        );

        $exception = QueryException::fromDriverError($pdoException, 'UPDATE users SET email = ?', [42]);

        expect($exception->getMessage())->toContain('something failed')
            ->and($exception->getMessage())->not->toContain('private@example.com')
            ->and($exception->getMessage())->not->toContain('DETAIL');
    });
});
