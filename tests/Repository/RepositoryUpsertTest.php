<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository\Upsert;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Timestamps;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Repository\Repository;

#[Table('upsert_subscribers')]
#[Timestamps]
class UpsertSubscriber extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'email_address')]
    public string $emailAddress = '';

    #[Column]
    public string $name = '';

    #[Column]
    public int $visits = 0;

    #[Column]
    public ?DateTimeImmutable $createdAt = null;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}

#[Table('upsert_other')]
class UpsertOther extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

class UpsertSubscriberRepository extends Repository
{
    protected const string ENTITY_CLASS = UpsertSubscriber::class;

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-05 12:00:00');
    }
}

/**
 * A query builder that records the table and upsert() arguments it receives.
 */
class RecordingUpsertBuilder
{
    public string $table = '';

    /** @var array<int, mixed> */
    public array $upsertArgs = [];
}

function makeUpsertRepository(
    RecordingUpsertBuilder $recorder,
    bool $withFactory = true,
): UpsertSubscriberRepository {
    $connection = test()->createStub(ConnectionInterface::class);
    $factory = null;

    if ($withFactory) {
        $builder = test()->createStub(QueryBuilderInterface::class);
        $builder->method('table')->willReturnCallback(function (string $table) use ($builder, $recorder) {
            $recorder->table = $table;

            return $builder;
        });
        $builder->method(
            'upsert',
        )->willReturnCallback(
            function (array $rows, array $uniqueBy, ?array $update) use ($recorder): int {
                $recorder->upsertArgs = [$rows, $uniqueBy, $update];

                return count($rows);
            },
        );
        $factory = test()->createStub(QueryBuilderFactoryInterface::class);
        $factory->method('create')->willReturn($builder);
    }

    return new UpsertSubscriberRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        $factory,
    );
}

function makeSubscriber(
    string $email,
    string $name,
    int $visits = 1,
): UpsertSubscriber {
    $subscriber = new UpsertSubscriber();
    $subscriber->emailAddress = $email;
    $subscriber->name = $name;
    $subscriber->visits = $visits;

    return $subscriber;
}

describe('Repository::upsert', function (): void {
    it('upserts entities through the query builder with mapped column names', function (): void {
        $recorder = new RecordingUpsertBuilder();
        $repository = makeUpsertRepository($recorder);

        $affected = $repository->upsert(
            [makeSubscriber('ada@example.com', 'Ada'), makeSubscriber('alan@example.com', 'Alan', 3)],
            ['emailAddress'],
            ['name', 'visits'],
        );

        [$rows, $uniqueBy, $update] = $recorder->upsertArgs;

        expect($affected)->toBe(2)
            ->and($recorder->table)->toBe('upsert_subscribers')
            ->and($uniqueBy)->toBe(['email_address'])
            ->and($update)->toBe(['name', 'visits'])
            ->and(array_column($rows, 'email_address'))->toBe(['ada@example.com', 'alan@example.com'])
            ->and($rows[0])->not->toHaveKey('id');
    });

    it('excludes the created-at column and the primary key from the default update list', function (): void {
        $recorder = new RecordingUpsertBuilder();
        $repository = makeUpsertRepository($recorder);

        $repository->upsert([makeSubscriber('ada@example.com', 'Ada')], ['emailAddress']);

        expect($recorder->upsertArgs[2])->toBe(['name', 'visits', 'updated_at']);
    });

    it('applies insert timestamps before upserting', function (): void {
        $recorder = new RecordingUpsertBuilder();
        $repository = makeUpsertRepository($recorder);
        $subscriber = makeSubscriber('ada@example.com', 'Ada');

        $repository->upsert([$subscriber], ['emailAddress']);

        expect($subscriber->createdAt?->format('Y-m-d H:i:s'))->toBe('2026-10-05 12:00:00')
            ->and($subscriber->updatedAt?->format('Y-m-d H:i:s'))->toBe('2026-10-05 12:00:00')
            ->and($recorder->upsertArgs[0][0]['created_at'])->toBe('2026-10-05 12:00:00');
    });

    it('refreshes a stale updated-at timestamp and keeps an existing created-at', function (): void {
        $recorder = new RecordingUpsertBuilder();
        $repository = makeUpsertRepository($recorder);
        $subscriber = makeSubscriber('ada@example.com', 'Ada');
        $subscriber->createdAt = new DateTimeImmutable('2020-01-01 00:00:00');
        $subscriber->updatedAt = new DateTimeImmutable('2020-01-01 00:00:00');

        $repository->upsert([$subscriber], ['emailAddress']);

        expect($subscriber->createdAt->format('Y-m-d H:i:s'))->toBe('2020-01-01 00:00:00')
            ->and($subscriber->updatedAt?->format('Y-m-d H:i:s'))->toBe('2026-10-05 12:00:00');
    });

    it('throws when an unknown property is named in uniqueBy', function (): void {
        makeUpsertRepository(new RecordingUpsertBuilder())->upsert([makeSubscriber('a@example.com', 'A')], ['email']);
    })->throws(RepositoryException::class, "has no mapped property 'email'");

    it('throws when an unknown property is named in update', function (): void {
        makeUpsertRepository(new RecordingUpsertBuilder())
            ->upsert([makeSubscriber('a@example.com', 'A')], ['emailAddress'], ['nickname']);
    })->throws(RepositoryException::class, "has no mapped property 'nickname'");

    it('throws when no query builder factory is configured', function (): void {
        makeUpsertRepository(new RecordingUpsertBuilder(), withFactory: false)
            ->upsert([makeSubscriber('a@example.com', 'A')], ['emailAddress']);
    })->throws(RepositoryException::class, 'does not have a query builder factory configured');

    it('rejects an empty batch', function (): void {
        makeUpsertRepository(new RecordingUpsertBuilder())->upsert([], ['emailAddress']);
    })->throws(BatchInsertException::class, 'Cannot insert an empty batch of entities');

    it('rejects a heterogeneous batch', function (): void {
        makeUpsertRepository(new RecordingUpsertBuilder())
            ->upsert([makeSubscriber('a@example.com', 'A'), new UpsertOther()], ['emailAddress']);
    })->throws(BatchInsertException::class, 'All entities in the batch must be of the same class');

    it('rejects a batch that mixes set and unset auto-increment keys', function (): void {
        $existing = makeSubscriber('a@example.com', 'A');
        $existing->id = 5;

        makeUpsertRepository(new RecordingUpsertBuilder())
            ->upsert([$existing, makeSubscriber('b@example.com', 'B')], ['emailAddress']);
    })->throws(BatchInsertException::class);
});
