<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use RuntimeException;

#[Table('settings')]
class QuotingSetting extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $key = '';

    #[Column]
    public string $group = '';

    #[Column]
    public int $order = 0;
}

/**
 * @extends Repository<QuotingSetting>
 */
class QuotingSettingRepository extends Repository
{
    protected const string ENTITY_CLASS = QuotingSetting::class;

    public function keyIsUnique(
        string $key,
        int $excludeId,
    ): bool {
        return $this->isColumnUnique('key', $key, $excludeId);
    }
}

#[Table('tokens')]
class QuotingToken extends Entity
{
    #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]
    public string $key;

    #[Column]
    public string $group = '';
}

/**
 * @extends Repository<QuotingToken>
 */
class QuotingTokenRepository extends Repository
{
    protected const string ENTITY_CLASS = QuotingToken::class;
}

/**
 * A connection that quotes identifiers with backticks (doubling embedded ones) and records every statement, so the
 * tests prove the repository quotes through the connection rather than with a hard-coded delimiter.
 */
class QuotingRecordingConnection implements ConnectionInterface
{
    /** @var list<array{type: string, sql: string, bindings: array<mixed>}> */
    public array $log = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function __construct(
        private readonly bool $returning = false,
    ) {}

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->log[] = ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings];

        return $this->rows;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->log[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

        return 1;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not used by these tests');
    }

    public function lastInsertId(): int
    {
        return 5;
    }

    public function driverName(): string
    {
        return 'recording';
    }

    public function supportsReturning(): bool
    {
        return $this->returning;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * The SQL of every recorded statement, in order.
     *
     * @return list<string>
     */
    public function statements(): array
    {
        return array_column($this->log, 'sql');
    }
}

function quotingRepository(
    QuotingRecordingConnection $connection,
): QuotingSettingRepository {
    $metadataFactory = new EntityMetadataFactory();

    return new QuotingSettingRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory));
}

function quotingSetting(
    string $key,
): QuotingSetting {
    $setting = new QuotingSetting();
    $setting->key = $key;
    $setting->group = 'mail';
    $setting->order = 1;

    return $setting;
}

describe('Repository identifier quoting', function (): void {
    it('quotes the table and primary key in find', function (): void {
        $connection = new QuotingRecordingConnection();

        quotingRepository($connection)->find(1);

        expect($connection->statements())->toBe(['SELECT * FROM `settings` WHERE `id` = ?']);
    });

    it('quotes the table in findAll', function (): void {
        $connection = new QuotingRecordingConnection();

        quotingRepository($connection)->findAll();

        expect($connection->statements())->toBe(['SELECT * FROM `settings`']);
    });

    it('quotes criteria columns in findBy and findOneBy', function (): void {
        $connection = new QuotingRecordingConnection();
        $repository = quotingRepository($connection);

        $repository->findBy(['group' => 'mail', 'order' => 1]);
        $repository->findOneBy(['key' => 'from']);

        expect($connection->statements())->toBe([
            'SELECT * FROM `settings` WHERE `group` = ? AND `order` = ?',
            'SELECT * FROM `settings` WHERE `key` = ? LIMIT 1',
        ]);
    });

    it('quotes columns in insert and the RETURNING column', function (): void {
        $plain = new QuotingRecordingConnection();
        $returning = new QuotingRecordingConnection(returning: true);
        $returning->rows = [['key' => 'a3f1']];
        $setting = quotingSetting('from');
        $token = new QuotingToken();
        $token->group = 'api';
        $metadataFactory = new EntityMetadataFactory();

        quotingRepository($plain)->save($setting);
        new QuotingTokenRepository($returning, $metadataFactory, new EntityHydrator($metadataFactory))->save($token);

        expect($plain->statements())->toBe(['INSERT INTO `settings` (`key`, `group`, `order`) VALUES (?, ?, ?)'])
            ->and($setting->id)->toBe(5)
            ->and($returning->statements())->toBe(['INSERT INTO `tokens` (`group`) VALUES (?) RETURNING `key`']);
    });

    it('reads the RETURNING key from the unquoted column name in the result row', function (): void {
        $connection = new QuotingRecordingConnection(returning: true);
        $connection->rows = [['key' => 'a3f1']];
        $token = new QuotingToken();
        $token->group = 'api';
        $metadataFactory = new EntityMetadataFactory();

        new QuotingTokenRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory))->save($token);

        expect($token->key)->toBe('a3f1');
    });

    it('quotes columns in insertBatch and the RETURNING column', function (): void {
        $connection = new QuotingRecordingConnection(returning: true);
        $connection->rows = [['id' => 1], ['id' => 2]];
        $first = quotingSetting('from');
        $second = quotingSetting('reply_to');

        quotingRepository($connection)->insertBatch([$first, $second]);

        expect($connection->statements())->toBe([
            'INSERT INTO `settings` (`key`, `group`, `order`) VALUES (?, ?, ?), (?, ?, ?) RETURNING `id`',
        ])
            ->and($first->id)->toBe(1)
            ->and($second->id)->toBe(2);
    });

    it('quotes SET columns and the primary key in update', function (): void {
        $connection = new QuotingRecordingConnection();
        $connection->rows = [['id' => 3, 'key' => 'from', 'group' => 'mail', 'order' => 1]];
        $repository = quotingRepository($connection);
        $setting = $repository->find(3);
        $setting->group = 'smtp';
        $setting->order = 2;

        $repository->save($setting);

        expect($connection->statements()[1])->toBe('UPDATE `settings` SET `group` = ?, `order` = ? WHERE `id` = ?');
    });

    it('quotes the table and primary key in delete, count, exists and existsBy', function (): void {
        $connection = new QuotingRecordingConnection();
        $repository = quotingRepository($connection);
        $setting = quotingSetting('from');
        $setting->id = 3;

        $repository->delete($setting);
        $repository->count();
        $repository->exists(3);
        $repository->existsBy(['key' => 'from', 'group' => 'mail']);

        expect($connection->statements())->toBe([
            'DELETE FROM `settings` WHERE `id` = ?',
            'SELECT COUNT(*) as aggregate FROM `settings`',
            'SELECT 1 FROM `settings` WHERE `id` = ? LIMIT 1',
            'SELECT 1 FROM `settings` WHERE `key` = ? AND `group` = ? LIMIT 1',
        ]);
    });

    it('quotes the column and primary key in isColumnUnique', function (): void {
        $connection = new QuotingRecordingConnection();

        quotingRepository($connection)->keyIsUnique('from', 3);

        expect($connection->statements())->toBe(['SELECT 1 FROM `settings` WHERE `key` = ? AND `id` != ? LIMIT 1']);
    });

    it('escapes an embedded delimiter in a criteria key', function (): void {
        $connection = new QuotingRecordingConnection();

        quotingRepository($connection)->findBy(['we`ird' => 1]);

        expect($connection->statements())->toBe(['SELECT * FROM `settings` WHERE `we``ird` = ?']);
    });
});
