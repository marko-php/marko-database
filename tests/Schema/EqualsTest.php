<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;

describe('Schema Value Objects Equality', function (): void {
    it('implements equals() method for diff comparison', function (): void {
        // Column equality
        $column1 = new Column(name: 'id', type: 'int', primaryKey: true);
        $column2 = new Column(name: 'id', type: 'int', primaryKey: true);
        $column3 = new Column(name: 'id', type: 'bigint', primaryKey: true);
        $column4 = new Column(name: 'user_id', type: 'int', primaryKey: true);

        expect($column1->equals($column2))->toBeTrue()
            ->and($column1->equals($column3))->toBeFalse() // Different type
            ->and($column1->equals($column4))->toBeFalse(); // Different name

        // Column with all properties
        $fullColumn1 = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
            nullable: true,
            default: null,
            unique: true,
        );
        $fullColumn2 = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
            nullable: true,
            default: null,
            unique: true,
        );
        $fullColumn3 = new Column(
            name: 'email',
            type: 'varchar',
            length: 100, // Different length
            nullable: true,
            default: null,
            unique: true,
        );

        expect($fullColumn1->equals($fullColumn2))->toBeTrue()
            ->and($fullColumn1->equals($fullColumn3))->toBeFalse();

        // Index equality
        $index1 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique);
        $index2 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique);
        $index3 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Btree);
        $index4 = new Index(name: 'idx_posts_title', columns: ['title'], type: IndexType::Unique);

        expect($index1->equals($index2))->toBeTrue()
            ->and($index1->equals($index3))->toBeFalse() // Different type
            ->and($index1->equals($index4))->toBeFalse(); // Different name and columns

        // ForeignKey equality
        $fk1 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $fk2 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $fk3 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'SET NULL', // Different action
        );

        expect($fk1->equals($fk2))->toBeTrue()
            ->and($fk1->equals($fk3))->toBeFalse();

        // Table equality
        $table1 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'int', primaryKey: true),
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );
        $table2 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'int', primaryKey: true),
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );
        $table3 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'bigint', primaryKey: true), // Different column type
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );

        expect($table1->equals($table2))->toBeTrue()
            ->and($table1->equals($table3))->toBeFalse();
    });
});

describe('Column default equality with expressions and literals', function (): void {
    it('treats an entity expression default and the same introspected expression as equal', function (): void {
        $entity = new Column(name: 'id', type: 'uuid', default: new Expression('gen_random_uuid()'));
        $database = new Column(name: 'id', type: 'uuid', default: new Expression('gen_random_uuid()'));

        expect($entity->equals($database))->toBeTrue();
    });

    it('treats a shortcut string default and the matching introspected expression as equal', function (): void {
        $entity = new Column(name: 'created_at', type: 'timestamp', default: 'NOW()');
        $database = new Column(name: 'created_at', type: 'timestamp', default: new Expression('now()'));

        expect($entity->equals($database))->toBeTrue();
    });

    it('treats different expression defaults as different', function (): void {
        $entity = new Column(name: 'id', type: 'uuid', default: new Expression('gen_random_uuid()'));
        $database = new Column(name: 'id', type: 'uuid', default: new Expression('uuid_generate_v4()'));

        expect($entity->equals($database))->toBeFalse();
    });

    it('treats a literal default and the same plain string as equal', function (): void {
        $entity = new Column(name: 'status', type: 'varchar', default: new Literal('draft'));
        $database = new Column(name: 'status', type: 'varchar', default: 'draft');

        expect($entity->equals($database))->toBeTrue();
    });

    it('treats a literal default and an expression with the same text as different', function (): void {
        $entity = new Column(name: 'label', type: 'varchar', default: new Literal('now()'));
        $expression = new Column(name: 'label', type: 'varchar', default: new Expression('now()'));
        $shortcut = new Column(name: 'label', type: 'varchar', default: 'now()');

        expect($entity->equals($expression))->toBeFalse()
            ->and($entity->equals($shortcut))->toBeFalse()
            ->and($entity->equals(new Column(name: 'label', type: 'varchar', default: new Literal('now()'))))
            ->toBeTrue();
    });

    it('exposes a public symmetric default comparison for generators', function (): void {
        $noDefault = new Column(name: 'status', type: 'varchar');
        $draft = new Column(name: 'status', type: 'varchar', default: 'draft');

        expect($noDefault->equals($draft))->toBeTrue()
            ->and($noDefault->hasSameDefaultAs($draft))->toBeFalse()
            ->and($draft->hasSameDefaultAs($noDefault))->toBeFalse()
            ->and((new Column(name: 'at', type: 'timestamp', default: 'NOW()'))
                ->hasSameDefaultAs(new Column(name: 'at', type: 'timestamp', default: new Expression('now()'))))
            ->toBeTrue();
    });
});
