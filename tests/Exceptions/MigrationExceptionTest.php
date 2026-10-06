<?php

declare(strict_types=1);

use Marko\Database\Exceptions\MigrationException;

describe('MigrationException', function (): void {
    it('builds a missing previous column exception naming the table and column', function (): void {
        $exception = MigrationException::missingPreviousColumn('posts', 'status');

        expect($exception->getMessage())
            ->toBe("Column 'posts.status' is modified, but the diff holds no previous definition for it")
            ->and($exception->getContext())->toContain('posts.status')
            ->and($exception->getSuggestion())->toContain('columnsToModifyFrom');
    });

    it('builds a nothing to modify exception naming the table, column and driver', function (): void {
        $exception = MigrationException::nothingToModify('posts', 'email', 'PostgreSQL');

        expect($exception->getMessage())
            ->toBe("Column 'posts.email' has no type, nullability or default change for PostgreSQL to apply")
            ->and($exception->getSuggestion())->toContain('index diff');
    });

    it('names the table, column and expression in the rejected default expression exception', function (): void {
        $exception = MigrationException::rejectedDefaultExpression(
            'posts',
            'expires_at',
            "now() + intervall '1 day'",
            'syntax error at or near "\'1 day\'"',
        );

        expect($exception->getMessage())
            ->toBe("The database rejects the default expression \"now() + intervall '1 day'\" of column "
                . "'posts.expires_at'")
            ->and($exception->getContext())->toContain('posts.expires_at')
            ->and($exception->getContext())->toContain('syntax error')
            ->and($exception->getSuggestion())->toContain('Expression');
    });

    it('builds a column change not supported exception naming the table, column and driver', function (): void {
        $exception = MigrationException::columnChangeNotSupported('posts', 'id', 'PostgreSQL', 'auto-increment');

        expect($exception->getMessage())
            ->toBe("Cannot change the auto-increment of column 'posts.id' in place on PostgreSQL")
            ->and($exception->getSuggestion())->toContain('by hand');
    });
});
