<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Diff;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;
use ReflectionClass;

describe('SchemaDiff', function (): void {
    it('creates readonly SchemaDiff value object', function (): void {
        $diff = new SchemaDiff();

        $reflection = new ReflectionClass($diff);
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('stores tables to create, drop, and alter', function (): void {
        $tableToCreate = new Table(
            name: 'new_table',
            columns: [new Column(name: 'id', type: 'INT')],
        );
        $tableToDrop = new Table(
            name: 'old_table',
            columns: [new Column(name: 'id', type: 'INT')],
        );
        $tableToAlter = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$tableToCreate],
            tablesToDrop: [$tableToDrop],
            tablesToAlter: ['posts' => $tableToAlter],
        );

        expect($diff->tablesToCreate)
            ->toHaveCount(1)
            ->and($diff->tablesToCreate[0]->name)->toBe('new_table')
            ->and($diff->tablesToDrop)->toHaveCount(1)
            ->and($diff->tablesToDrop[0]->name)->toBe('old_table')
            ->and($diff->tablesToAlter)->toHaveKey('posts')
            ->and($diff->tablesToAlter['posts']->tableName)->toBe('posts');
    });

    it('reports isEmpty correctly', function (): void {
        $emptyDiff = new SchemaDiff();
        expect($emptyDiff->isEmpty())->toBeTrue();

        $diffWithCreate = new SchemaDiff(
            tablesToCreate: [new Table(name: 'new', columns: [])],
        );
        expect($diffWithCreate->isEmpty())->toBeFalse();

        $diffWithDrop = new SchemaDiff(
            tablesToDrop: [new Table(name: 'old', columns: [])],
        );
        expect($diffWithDrop->isEmpty())->toBeFalse();

        $diffWithAlter = new SchemaDiff(
            tablesToAlter: ['posts' => new TableDiff(tableName: 'posts')],
        );
        expect($diffWithAlter->isEmpty())->toBeFalse();
    });

    it('reports hasDestructiveChanges correctly', function (): void {
        $emptyDiff = new SchemaDiff();
        expect($emptyDiff->hasDestructiveChanges())->toBeFalse();

        $diffWithCreate = new SchemaDiff(
            tablesToCreate: [new Table(name: 'new', columns: [])],
        );
        expect($diffWithCreate->hasDestructiveChanges())->toBeFalse();

        $diffWithDrop = new SchemaDiff(
            tablesToDrop: [new Table(name: 'old', columns: [])],
        );
        expect($diffWithDrop->hasDestructiveChanges())->toBeTrue();

        $diffWithDestructiveAlter = new SchemaDiff(
            tablesToAlter: [
                'posts' => new TableDiff(
                    tableName: 'posts',
                    columnsToDrop: [new Column(name: 'old_col', type: 'VARCHAR')],
                ),
            ],
        );
        expect($diffWithDestructiveAlter->hasDestructiveChanges())->toBeTrue();
    });

    it('keeps only the destructive changes in destructiveOnly()', function (): void {
        $diff = new SchemaDiff(
            tablesToCreate: [new Table(name: 'new', columns: [])],
            tablesToDrop: [new Table(name: 'old', columns: [])],
            tablesToAlter: [
                'posts' => new TableDiff(
                    tableName: 'posts',
                    columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
                    columnsToDrop: [new Column(name: 'old_col', type: 'VARCHAR')],
                    indexesToDrop: [new Index(name: 'posts_old_idx', columns: ['old_col'])],
                ),
                'tags' => new TableDiff(
                    tableName: 'tags',
                    columnsToAdd: [new Column(name: 'name', type: 'VARCHAR')],
                ),
            ],
        );

        $destructive = $diff->destructiveOnly();

        expect($destructive->tablesToCreate)->toBe([])
            ->and($destructive->tablesToDrop)->toHaveCount(1)
            ->and(array_keys($destructive->tablesToAlter))->toBe(['posts'])
            ->and($destructive->tablesToAlter['posts']->columnsToAdd)->toBe([])
            ->and($destructive->tablesToAlter['posts']->columnsToDrop)->toHaveCount(1)
            ->and($destructive->tablesToAlter['posts']->indexesToDrop)->toHaveCount(1);
    });
});
