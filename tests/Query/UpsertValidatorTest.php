<?php

declare(strict_types=1);

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Exceptions\UpsertException;
use Marko\Database\Query\UpsertValidator;

describe('UpsertValidator', function (): void {
    it('updates every non-unique column when no update list is given', function (): void {
        $update = UpsertValidator::validate(
            [['email' => 'a@example.com', 'name' => 'Ada', 'visits' => 1]],
            ['email'],
            null,
        );

        expect($update)->toBe(['name', 'visits']);
    });

    it('returns an explicit update list unchanged', function (): void {
        $update = UpsertValidator::validate(
            [['email' => 'a@example.com', 'name' => 'Ada', 'visits' => 1]],
            ['email'],
            ['visits'],
        );

        expect($update)->toBe(['visits']);
    });

    it('returns an empty update list when asked to update nothing', function (): void {
        expect(UpsertValidator::validate([['email' => 'a@example.com']], ['email'], []))->toBe([]);
    });

    it('rejects an empty set of rows', function (): void {
        UpsertValidator::validate([], ['email'], null);
    })->throws(UpsertException::class, 'Cannot upsert an empty set of rows');

    it('rejects an empty uniqueBy list', function (): void {
        UpsertValidator::validate([['email' => 'a@example.com']], [], null);
    })->throws(UpsertException::class, 'requires at least one conflict column');

    it('rejects rows with different columns', function (): void {
        UpsertValidator::validate(
            [['email' => 'a@example.com', 'name' => 'Ada'], ['email' => 'b@example.com']],
            ['email'],
            null,
        );
    })->throws(UpsertException::class, 'Row 1 has a different set of columns than row 0');

    it('rejects a conflict column that is not inserted', function (): void {
        UpsertValidator::validate([['name' => 'Ada']], ['email'], null);
    })->throws(UpsertException::class, "Conflict column 'email' is not one of the inserted columns");

    it('rejects an update column that is not inserted', function (): void {
        UpsertValidator::validate([['email' => 'a@example.com']], ['email'], ['name']);
    })->throws(UpsertException::class, "Update column 'name' is not one of the inserted columns");

    it('rejects an invalid column identifier', function (): void {
        UpsertValidator::validate([['email; DROP TABLE users' => 'x']], ['email; DROP TABLE users'], null);
    })->throws(InvalidColumnException::class);
});
