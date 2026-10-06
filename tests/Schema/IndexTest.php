<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use ReflectionClass;

describe('Index', function (): void {
    it('creates readonly Index class with name, columns, and type (btree, unique, fulltext)', function (): void {
        // Default btree index
        $btreeIndex = new Index(
            name: 'idx_posts_author_id',
            columns: ['author_id'],
        );

        expect($btreeIndex->name)->toBe('idx_posts_author_id')
            ->and($btreeIndex->columns)->toBe(['author_id'])
            ->and($btreeIndex->type)->toBe(IndexType::Btree);

        // Unique index
        $uniqueIndex = new Index(
            name: 'idx_posts_slug',
            columns: ['slug'],
            type: IndexType::Unique,
        );

        expect($uniqueIndex->name)->toBe('idx_posts_slug')
            ->and($uniqueIndex->columns)->toBe(['slug'])
            ->and($uniqueIndex->type)->toBe(IndexType::Unique);

        // Fulltext index
        $fulltextIndex = new Index(
            name: 'idx_posts_content',
            columns: ['title', 'body'],
            type: IndexType::Fulltext,
        );

        expect($fulltextIndex->name)->toBe('idx_posts_content')
            ->and($fulltextIndex->columns)->toBe(['title', 'body'])
            ->and($fulltextIndex->type)->toBe(IndexType::Fulltext);

        // Verify it's a readonly class
        $reflection = new ReflectionClass($btreeIndex);
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('treats indexes with different where predicates as not equal', function (): void {
        $partial = new Index(name: 'idx_live', columns: ['status'], where: "status = 'live'");
        $samePartial = new Index(name: 'idx_live', columns: ['status'], where: "status = 'live'");
        $otherPartial = new Index(name: 'idx_live', columns: ['status'], where: "status = 'draft'");
        $full = new Index(name: 'idx_live', columns: ['status']);

        expect($partial->equals($samePartial))->toBeTrue()
            ->and($partial->equals($otherPartial))->toBeFalse()
            ->and($partial->equals($full))->toBeFalse();
    });

    it('ignores whether an index backs a constraint when comparing', function (): void {
        $index = new Index(name: 'users_email_key', columns: ['email'], type: IndexType::Unique);
        $constraint = new Index(
            name: 'users_email_key',
            columns: ['email'],
            type: IndexType::Unique,
            constraint: true,
        );

        expect($index->constraint)->toBeFalse()
            ->and($constraint->constraint)->toBeTrue()
            ->and($index->equals($constraint))->toBeTrue();
    });
});
