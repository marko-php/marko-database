<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\ConstraintViolationException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;

function constraintPdoError(
    string $sqlState,
    string $driverMessage,
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: Integrity constraint violation: 7 $driverMessage");
    $exception->errorInfo = [$sqlState, 7, $driverMessage];

    return $exception;
}

describe('ConstraintViolationException', function (): void {
    it('exposes constraint name, table and column on constraint violations', function (): void {
        $exception = NotNullConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23502', 'ERROR:  null value in column "email"'),
            sql: 'INSERT INTO users (email) VALUES (?)',
            bindings: [null],
            table: 'users',
            column: 'email',
        );

        expect($exception)->toBeInstanceOf(ConstraintViolationException::class)
            ->and($exception)->toBeInstanceOf(QueryException::class)
            ->and($exception->constraintName())->toBeNull()
            ->and($exception->table())->toBe('users')
            ->and($exception->column())->toBe('email')
            ->and($exception->sqlState())->toBe('23502')
            ->and($exception->getMessage())->toBe(
                "Not-null constraint violated: column 'email' on table 'users' cannot be null",
            );
    });

    it('builds a unique violation message naming the constraint and table with a suggestion', function (): void {
        $previous = constraintPdoError(
            '23505',
            "ERROR:  duplicate key value violates unique constraint \"users_email_unique\"\n"
            . 'DETAIL:  Key (email)=(taken@example.com) already exists.',
        );

        $exception = UniqueConstraintViolationException::fromDriverError(
            previous: $previous,
            sql: 'INSERT INTO users (email) VALUES (?)',
            bindings: ['taken@example.com'],
            constraintName: 'users_email_unique',
            table: 'users',
        );

        expect($exception->getMessage())->toBe("Unique constraint 'users_email_unique' violated on table 'users'")
            ->and($exception->getMessage())->not->toContain('taken@example.com')
            ->and($exception->getContext())->toContain('INSERT INTO users (email) VALUES (?)')
            ->and($exception->getContext())->not->toContain('taken@example.com')
            ->and($exception->getSuggestion())->toContain('existsBy()')
            ->and($exception->getSuggestion())->toContain('UniqueConstraintViolationException')
            ->and($exception->getPrevious())->toBe($previous)
            ->and($exception->bindings())->toBe(['taken@example.com']);
    });

    it('leaves unknown parts out of the message', function (): void {
        $exception = ForeignKeyConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23503', 'ERROR:  foreign key violation'),
            sql: 'DELETE FROM users WHERE id = ?',
            bindings: [1],
        );

        expect($exception->getMessage())->toBe('Foreign key constraint violated')
            ->and($exception->constraintName())->toBeNull()
            ->and($exception->table())->toBeNull();
    });

    it('builds foreign key and check violation messages', function (): void {
        $foreignKey = ForeignKeyConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23503', 'ERROR:  fk'),
            sql: 'DELETE FROM users WHERE id = ?',
            bindings: [1],
            constraintName: 'posts_user_id_fkey',
            table: 'posts',
        );
        $check = CheckConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23514', 'ERROR:  check'),
            sql: 'INSERT INTO products (price) VALUES (?)',
            bindings: [-1],
            constraintName: 'products_price_check',
            table: 'products',
        );

        expect($foreignKey->getMessage())->toBe(
            "Foreign key constraint 'posts_user_id_fkey' violated on table 'posts'",
        )
            ->and($foreignKey->getSuggestion())->toContain('ForeignKeyConstraintViolationException')
            ->and($check->getMessage())->toBe("Check constraint 'products_price_check' violated on table 'products'")
            ->and($check->getSuggestion())->not->toBeEmpty();
    });

    it('maps unique and foreign key violations to 409 with a generic body', function (): void {
        $unique = UniqueConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23505', 'ERROR:  duplicate'),
            sql: 'INSERT INTO users (email) VALUES (?)',
            bindings: ['taken@example.com'],
            constraintName: 'users_email_unique',
            table: 'users',
        );
        $foreignKey = ForeignKeyConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23503', 'ERROR:  fk'),
            sql: 'DELETE FROM users WHERE id = ?',
            bindings: [1],
            constraintName: 'posts_user_id_fkey',
            table: 'posts',
        );

        foreach ([$unique, $foreignKey] as $exception) {
            expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
                ->and($exception->getStatusCode())->toBe(409)
                ->and($exception->getHeaders())->toBe([])
                ->and($exception->getResponseData())->toBe(['message' => 'Conflict.']);
        }
    });

    it('keeps not-null and check violations out of the HTTP mapping', function (): void {
        $notNull = NotNullConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23502', 'ERROR:  null'),
            sql: 'INSERT INTO users (email) VALUES (?)',
            bindings: [null],
            column: 'email',
        );
        $check = CheckConstraintViolationException::fromDriverError(
            previous: constraintPdoError('23514', 'ERROR:  check'),
            sql: 'INSERT INTO products (price) VALUES (?)',
            bindings: [-1],
        );

        expect($notNull)->not->toBeInstanceOf(HttpExceptionInterface::class)
            ->and($check)->not->toBeInstanceOf(HttpExceptionInterface::class);
    });
});
