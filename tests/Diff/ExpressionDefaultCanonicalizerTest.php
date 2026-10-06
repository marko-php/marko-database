<?php

declare(strict_types=1);

use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Diff\Fixtures\CountingMatcherIntrospector;

/**
 * A one-table schema keyed by table name, as the commands hand it to the diff.
 *
 * @return array<string, Table>
 */
function canonicalizerSchema(
    Column ...$columns,
): array {
    return [
        'events' => new Table(
            name: 'events',
            columns: [new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true), ...$columns],
        ),
    ];
}

function expiresAt(
    mixed $default,
    string $type = 'timestamp',
): Column {
    return new Column(name: 'expires_at', type: $type, default: $default);
}

describe('ExpressionDefaultCanonicalizer', function (): void {
    it(
        'replaces the entity expression default with the database default when the database stores the same expression',
        function (): void {
            $introspector = new CountingMatcherIntrospector(fn (): bool => true);
            $entity = canonicalizerSchema(expiresAt(new Expression("now() + interval '1 day'")));
            $database = canonicalizerSchema(expiresAt(new Expression("(now() + '1 day'::interval)")));

            $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

            expect($canonical['events']->columns[1]->default)->toEqual(new Expression("(now() + '1 day'::interval)"))
                ->and(new DiffCalculator()->calculate($canonical, $database)->isEmpty())->toBeTrue()
                ->and($introspector->probes)->toBe([
                    ['table' => 'events', 'column' => 'expires_at', 'sql' => "now() + interval '1 day'"],
                ]);
        },
    );

    it('keeps the entity expression default when the database stores a different expression', function (): void {
        $introspector = new CountingMatcherIntrospector(fn (): bool => false);
        $entity = canonicalizerSchema(expiresAt(new Expression("now() + interval '2 days'")));
        $database = canonicalizerSchema(expiresAt(new Expression("(now() + '1 day'::interval)")));

        $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

        expect($canonical)->toEqual($entity)
            ->and(new DiffCalculator()->calculate($canonical, $database)->tablesToAlter['events']->columnsToModify)
            ->toHaveKey('expires_at')
            ->and($introspector->probes)->toHaveCount(1);
    });

    it('does not probe columns whose defaults already compare equal', function (): void {
        $introspector = new CountingMatcherIntrospector(fn (): bool => true);
        $entity = canonicalizerSchema(
            expiresAt(new Expression('CURRENT_TIMESTAMP')),
            new Column(name: 'token', type: 'uuid', default: new Expression('gen_random_uuid()')),
        );
        $database = canonicalizerSchema(
            expiresAt(new Expression('current_timestamp')),
            new Column(name: 'token', type: 'uuid', default: new Expression('(GEN_RANDOM_UUID())')),
        );

        $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

        expect($canonical)->toEqual($entity)
            ->and($introspector->probes)->toBe([]);
    });

    it('does not probe columns whose entity default is not an expression', function (): void {
        $introspector = new CountingMatcherIntrospector(fn (): bool => true);
        $entity = canonicalizerSchema(
            new Column(name: 'status', type: 'varchar', default: 'draft'),
            expiresAt('CURRENT_TIMESTAMP'),
        );
        $database = canonicalizerSchema(
            new Column(name: 'status', type: 'varchar', default: 'published'),
            expiresAt(new Expression('now()')),
        );

        new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

        expect($introspector->probes)->toBe([]);
    });

    it('does not probe columns the database does not have or has no default for', function (): void {
        $introspector = new CountingMatcherIntrospector(fn (): bool => true);
        $entity = canonicalizerSchema(
            expiresAt(new Expression("now() + interval '1 day'")),
            new Column(name: 'starts_at', type: 'timestamp', default: new Expression("now() + interval '1 hour'")),
        );
        $entity['logs'] = new Table(name: 'logs', columns: [expiresAt(new Expression("now() + interval '1 day'"))]);
        $database = canonicalizerSchema(expiresAt(null));

        $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

        expect($canonical)->toEqual($entity)
            ->and($introspector->probes)->toBe([]);
    });

    it('does not probe columns that differ in more than their default', function (): void {
        $introspector = new CountingMatcherIntrospector(fn (): bool => true);
        $entity = canonicalizerSchema(
            new Column(
                name: 'expires_at',
                type: 'timestamp',
                nullable: true,
                default: new Expression("now() + interval '1 day'"),
            ),
        );
        $database = canonicalizerSchema(expiresAt(new Expression("(now() + '1 day'::interval)")));

        $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);

        expect($canonical)->toEqual($entity)
            ->and($introspector->probes)->toBe([]);
    });

    it(
        'returns the entity schema unchanged when the introspector cannot match expression defaults',
        function (): void {
            $introspector = new class () implements IntrospectorInterface
            {
                public function getTables(): array
                {
                    return [];
                }

                public function getTable(
                    string $name,
                ): ?Table {
                    return null;
                }

                public function tableExists(
                    string $name,
                ): bool {
                    return false;
                }

                public function getColumns(
                    string $table,
                ): array {
                    return [];
                }

                public function getIndexes(
                    string $table,
                ): array {
                    return [];
                }

                public function getForeignKeys(
                    string $table,
                ): array {
                    return [];
                }

                public function getPrimaryKey(
                    string $table,
                ): array {
                    return [];
                }
            };
            $entity = canonicalizerSchema(expiresAt(new Expression("now() + interval '1 day'")));
            $database = canonicalizerSchema(expiresAt(new Expression("(now() + '1 day'::interval)")));

            expect(new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database))
                ->toBe($entity);
        },
    );

    it("lets the matcher's MigrationException for a rejected expression propagate", function (): void {
        $introspector = new CountingMatcherIntrospector(
            function (string $table, string $column, Expression $expression): bool {
                throw ExpressionDefaultProbeException::rejected($table, $column, $expression->sql, 'syntax error');
            },
        );
        $entity = canonicalizerSchema(expiresAt(new Expression("now() + intervall '1 day'")));
        $database = canonicalizerSchema(expiresAt(new Expression("(now() + '1 day'::interval)")));

        expect(fn () => new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database))
            ->toThrow(MigrationException::class, "now() + intervall '1 day'");
    });
});
