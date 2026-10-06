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

    it('builds a column change not supported exception naming the table, column and driver', function (): void {
        $exception = MigrationException::columnChangeNotSupported('posts', 'id', 'PostgreSQL', 'auto-increment');

        expect($exception->getMessage())
            ->toBe("Cannot change the auto-increment of column 'posts.id' in place on PostgreSQL")
            ->and($exception->getSuggestion())->toContain('by hand');
    });

    it(
        'names the table, the existing key columns and the new key columns when a primary key already exists',
        function (): void {
            $exception = MigrationException::primaryKeyAlreadyExists('post_tags', ['post_id', 'tag_id'], ['id']);

            expect($exception->getMessage())
                ->toBe("Cannot add primary key column 'id' to table 'post_tags', which already has a primary key on "
                    . "'post_id', 'tag_id'")
                ->and($exception->getSuggestion())->toContain('by hand');
        },
    );

    it('lists every new key column when several are added to a table that has a primary key', function (): void {
        $exception = MigrationException::primaryKeyAlreadyExists('post_tags', ['id'], ['post_id', 'tag_id']);

        expect($exception->getMessage())
            ->toBe("Cannot add primary key columns 'post_id', 'tag_id' to table 'post_tags', which already has a "
                . "primary key on 'id'");
    });
});
