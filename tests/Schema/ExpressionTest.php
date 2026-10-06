<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Attributes\Column as ColumnAttribute;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\Literal;
use ReflectionProperty;

class ExpressionDefaultEntityFixture
{
    #[ColumnAttribute(type: 'uuid', default: new Expression('gen_random_uuid()'))]
    public string $id;

    #[ColumnAttribute(default: new Literal('now()'))]
    public string $label;
}

describe('Expression', function (): void {
    it('holds the raw SQL of an expression default', function (): void {
        $expression = new Expression('gen_random_uuid()');

        expect($expression->sql)->toBe('gen_random_uuid()');
    });

    it('rejects an empty expression loudly', function (): void {
        expect(fn () => new Expression('   '))
            ->toThrow(MigrationException::class, 'A default expression cannot be empty');
    });

    it('recognizes bare timestamp keywords and their precision forms as shortcuts', function (): void {
        foreach ([
            'CURRENT_TIMESTAMP',
            'current_timestamp',
            'CURRENT_TIMESTAMP(6)',
            'CURRENT_DATE',
            'CURRENT_TIME',
            'LOCALTIMESTAMP',
            'LOCALTIMESTAMP(3)',
            'LOCALTIME',
        ] as $value) {
            expect(Expression::isShortcut($value))->toBeTrue("'$value' should be a shortcut");
        }
    });

    it('recognizes a zero-argument function call as a shortcut', function (): void {
        foreach (['NOW()', 'now()', 'gen_random_uuid()', 'uuid_generate_v4()', 'UUID()'] as $value) {
            expect(Expression::isShortcut($value))->toBeTrue("'$value' should be a shortcut");
        }
    });

    it('does not treat ordinary strings or function calls with arguments as shortcuts', function (): void {
        foreach ([
            'draft',
            'NULL',
            'Nullify',
            'NOW() please',
            'CURRENT_TIMESTAMPS',
            "concat('a', 'b')",
            '(UUID())',
            "now() + interval '1 day'",
            '',
        ] as $value) {
            expect(Expression::isShortcut($value))->toBeFalse("'$value' should not be a shortcut");
        }
    });

    it('compares expressions ignoring case, whitespace and redundant outer parentheses', function (): void {
        expect((new Expression('NOW()'))->equals(new Expression('now()')))->toBeTrue()
            ->and((new Expression('(UUID())'))->equals(new Expression('uuid()')))->toBeTrue()
            ->and((new Expression('((json_array()))'))->equals(new Expression('JSON_ARRAY()')))->toBeTrue()
            ->and((new Expression("now()  +  interval '1 day'"))->equals(new Expression("now() + interval '1 day'")))
            ->toBeTrue()
            ->and((new Expression('(a) + (b)'))->equals(new Expression('a) + (b')))->toBeFalse()
            ->and((new Expression('now()'))->equals(new Expression('gen_random_uuid()')))->toBeFalse();
    });

    it('strips parentheses that wrap the whole expression without changing its case', function (): void {
        expect(Expression::unwrap("((CONCAT('A', 'b')))"))->toBe("CONCAT('A', 'b')")
            ->and(Expression::unwrap(' ( now() + interval 1 day ) '))->toBe('now() + interval 1 day')
            ->and(Expression::unwrap('NOW()'))->toBe('NOW()');
    });

    it('keeps parentheses that do not wrap the whole expression', function (): void {
        expect(Expression::unwrap('(a) + (b)'))->toBe('(a) + (b)')
            ->and(Expression::unwrap('((a) + (b))'))->toBe('(a) + (b)');
    });

    it('is usable as a Column attribute default', function (): void {
        $id = new ReflectionProperty(ExpressionDefaultEntityFixture::class, 'id')
            ->getAttributes(ColumnAttribute::class)[0]
            ->newInstance();
        $label = new ReflectionProperty(ExpressionDefaultEntityFixture::class, 'label')
            ->getAttributes(ColumnAttribute::class)[0]
            ->newInstance();

        expect($id->default)->toEqual(new Expression('gen_random_uuid()'))
            ->and($label->default)->toEqual(new Literal('now()'));
    });
});

describe('Literal', function (): void {
    it('holds the value of a literal default', function (): void {
        expect((new Literal('gen_random_uuid()'))->value)->toBe('gen_random_uuid()');
    });
});
