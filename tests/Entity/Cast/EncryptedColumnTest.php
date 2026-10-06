<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Cast;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Core\Container\Container;
use Marko\Database\Attributes\Cast;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Encrypted;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Cast\CastResolver;
use Marko\Database\Entity\Cast\DateTimeCast;
use Marko\Database\Entity\Cast\EncryptedCast;
use Marko\Database\Entity\Cast\JsonCast;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Repository\Repository;
use Marko\Database\Tests\Entity\Cast\Fixtures\RecordingSqliteConnection;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;
use Marko\Testing\Fake\FakeEncryptor;

#[Table('secret_records')]
class SecretRecord extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $label = '';

    #[Column]
    #[Encrypted]
    public string $secret = '';

    #[Column]
    #[Encrypted]
    public string $hint = '';

    #[Column]
    #[Encrypted]
    public ?int $pin = null;

    #[Column]
    #[Encrypted]
    public bool $flag = false;

    #[Column]
    #[Encrypted]
    public array $payload = [];

    #[Column]
    #[Encrypted]
    public ?DateTimeImmutable $seenAt = null;
}

class SecretRecordRepository extends Repository
{
    protected const string ENTITY_CLASS = SecretRecord::class;
}

#[Table('bad_encrypted_pk')]
class EncryptedPrimaryKeyEntity extends Entity
{
    #[Column(primaryKey: true)]
    #[Encrypted]
    public string $id = '';
}

#[Table('bad_encrypted_unique')]
class EncryptedUniqueEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    #[Encrypted]
    public string $secret = '';
}

#[Table('bad_encrypted_index')]
#[Index(name: 'idx_secret', columns: ['secret'])]
class EncryptedIndexedEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    #[Encrypted]
    public string $secret = '';
}

#[Table('schema_types')]
class SchemaTypesEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    #[Encrypted]
    public int $count = 0;

    #[Column]
    #[Encrypted]
    public string $name = '';

    #[Column(type: 'char', length: 26)]
    #[Cast(JsonCast::class)]
    public array $declared = [];

    #[Column]
    #[Cast(DateTimeCast::class)]
    public ?DateTimeImmutable $inferred = null;
}

function encryptedContainer(
    FakeEncryptor $encryptor = new FakeEncryptor(),
): Container {
    $container = new Container();
    $container->instance(EncryptorInterface::class, $encryptor);

    return $container;
}

function encryptedConnection(): RecordingSqliteConnection
{
    $connection = new RecordingSqliteConnection();
    $connection->createTable(
        'CREATE TABLE secret_records (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT, secret TEXT, hint TEXT, '
        . 'pin TEXT, flag TEXT, payload TEXT, seen_at TEXT)',
    );

    return $connection;
}

function encryptedRepository(
    RecordingSqliteConnection $connection,
    FakeEncryptor $encryptor = new FakeEncryptor(),
): SecretRecordRepository {
    $container = encryptedContainer($encryptor);
    $metadataFactory = new EntityMetadataFactory($container);
    $hydrator = new EntityHydrator($metadataFactory, new CastResolver($container));

    return new SecretRecordRepository($connection, $metadataFactory, $hydrator);
}

it('stores ciphertext rather than plaintext', function (): void {
    $connection = encryptedConnection();
    $repository = encryptedRepository($connection);

    $record = new SecretRecord();
    $record->label = 'visible';
    $record->secret = 'top-secret';
    $repository->save($record);

    $stored = $connection->lastInsertValues();

    expect($stored['secret'])->not->toBe('top-secret')
        ->and($stored['secret'])->toStartWith('fake-encrypted:')
        ->and($stored['label'])->toBe('visible');
});

it('hydrates the plaintext', function (): void {
    $connection = encryptedConnection();
    $repository = encryptedRepository($connection);

    $record = new SecretRecord();
    $record->secret = 'top-secret';
    $repository->save($record);

    $loaded = $repository->find($record->id);

    expect($loaded->secret)->toBe('top-secret');
});

it('throws a clear exception at parse time when no encryptor is bound', function (): void {
    $factory = new EntityMetadataFactory(new Container());

    $factory->parse(SecretRecord::class);
})->throws(EntityException::class, 'no EncryptorInterface implementation is bound');

it('throws a clear exception at parse time when the metadata factory has no container', function (): void {
    $factory = new EntityMetadataFactory();

    $factory->parse(SecretRecord::class);
})->throws(EntityException::class, 'no EncryptorInterface implementation is bound');

it('accepts an encryptor registered as an instance', function (): void {
    $container = new Container();
    $container->instance(EncryptorInterface::class, new FakeEncryptor());

    $metadata = new EntityMetadataFactory($container)->parse(SecretRecord::class);

    expect($metadata->getProperty('secret')->encrypted)->toBeTrue();
});

it('generates a text column for encrypted properties regardless of PHP type', function (): void {
    $metadata = new EntityMetadataFactory(encryptedContainer())->parse(SchemaTypesEntity::class);
    $table = new SchemaBuilder()->build($metadata);
    $types = [];
    foreach ($table->columns as $column) {
        $types[$column->name] = $column->type;
    }

    expect($types['count'])->toBe('text')
        ->and($types['name'])->toBe('text');
});

it('generates the declared column type for cast properties', function (): void {
    $metadata = new EntityMetadataFactory(encryptedContainer())->parse(SchemaTypesEntity::class);
    $table = new SchemaBuilder()->build($metadata);
    $columns = [];
    foreach ($table->columns as $column) {
        $columns[$column->name] = $column;
    }

    expect($columns['declared']->type)->toBe('char')
        ->and($columns['declared']->length)->toBe(26)
        // A cast property without an explicit type falls back to the inferred default (varchar)
        ->and($columns['inferred']->type)->toBe('varchar');
});

it('rejects combining Encrypted with a primary key', function (): void {
    new EntityMetadataFactory(encryptedContainer())->parse(EncryptedPrimaryKeyEntity::class);
})->throws(EntityException::class, 'cannot be #[Encrypted]');

it('rejects combining Encrypted with a unique column or an Index', function (): void {
    $factory = new EntityMetadataFactory(encryptedContainer());

    expect(fn () => $factory->parse(EncryptedUniqueEntity::class))
        ->toThrow(EntityException::class, 'unique column or an #[Index]')
        ->and(fn () => $factory->parse(EncryptedIndexedEntity::class))
        ->toThrow(EntityException::class, 'unique column or an #[Index]');
});

it('wraps decryption failures in an entity exception naming the entity, property and column', function (): void {
    $connection = encryptedConnection();
    $connection->execute(
        'INSERT INTO secret_records (label, secret) VALUES (?, ?)',
        ['x', 'not-valid-ciphertext'],
    );
    $repository = encryptedRepository($connection);

    try {
        $repository->find(1);
        $this->fail('Expected EntityException');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain(SecretRecord::class)
            ->and($e->getMessage())->toContain("'secret'")
            ->and($e->getMessage())->toContain("column 'secret'")
            ->and($e->getPrevious())->toBeInstanceOf(DecryptionException::class);
    }
});

it('round-trips encrypted int, bool, array and DateTimeImmutable properties', function (): void {
    $connection = encryptedConnection();
    $repository = encryptedRepository($connection);

    $record = new SecretRecord();
    $record->pin = 4821;
    $record->flag = true;
    $record->payload = ['a' => 1, 'b' => [2, 3]];
    $record->seenAt = new DateTimeImmutable('2026-03-01 10:15:00', new DateTimeZone('UTC'));
    $repository->save($record);

    $loaded = $repository->find($record->id);

    expect($loaded->pin)->toBe(4821)
        ->and($loaded->flag)->toBeTrue()
        ->and($loaded->payload)->toBe(['a' => 1, 'b' => [2, 3]])
        ->and($loaded->seenAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($loaded->seenAt->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:15:00');
});

it('does not mark an unchanged encrypted property dirty', function (): void {
    $connection = encryptedConnection();
    $repository = encryptedRepository($connection);

    $record = new SecretRecord();
    $record->secret = 'top-secret';
    $record->payload = ['a' => 1];
    $repository->save($record);

    $loaded = $repository->find($record->id);
    $container = encryptedContainer();
    $hydrator = new EntityHydrator(castResolver: new CastResolver($container));
    $metadata = new EntityMetadataFactory($container)->parse(SecretRecord::class);
    $fresh = $hydrator->hydrate(SecretRecord::class, $connection->query('SELECT * FROM secret_records')[0], $metadata);

    expect($hydrator->getDirtyProperties($fresh, $metadata))->toBeEmpty();

    $fresh->secret = 'changed';

    expect($hydrator->getDirtyProperties($fresh, $metadata))->toBe(['secret'])
        ->and($loaded->secret)->toBe('top-secret');
});

it('throws when query criteria target an encrypted property', function (): void {
    $repository = encryptedRepository(encryptedConnection());

    expect(fn () => $repository->findBy(['secret' => 'x']))
        ->toThrow(EntityException::class, 'encrypted property')
        ->and(fn () => $repository->findOneBy(['secret' => 'x']))
        ->toThrow(EntityException::class, 'encrypted property')
        ->and(fn () => $repository->existsBy(['secret' => 'x']))
        ->toThrow(EntityException::class, 'encrypted property');
});

it('binds each encrypted value to its table and column as associated data', function (): void {
    $encryptor = new FakeEncryptor();
    $repository = encryptedRepository(encryptedConnection(), $encryptor);

    $record = new SecretRecord();
    $record->secret = 'top-secret';
    $repository->save($record);

    $encryptor->assertEncrypted('top-secret', 'secret_records.secret');
    $encryptor->assertEncrypted(aad: 'secret_records.hint');

    expect(array_column($encryptor->encrypted, 'aad'))->not->toContain('');
});

it('refuses to hydrate ciphertext copied from another encrypted column', function (): void {
    $connection = encryptedConnection();
    $repository = encryptedRepository($connection);

    $record = new SecretRecord();
    $record->secret = 'top-secret';
    $record->hint = 'harmless';
    $repository->save($record);

    $connection->execute('UPDATE secret_records SET hint = secret WHERE id = ?', [$record->id]);

    try {
        encryptedRepository($connection)->find($record->id);
        $this->fail('Expected EntityException');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain("column 'hint'")
            ->and($e->getPrevious())->toBeInstanceOf(DecryptionException::class);
    }
});

it('records the table name on property metadata', function (): void {
    $metadata = new EntityMetadataFactory(encryptedContainer())->parse(SecretRecord::class);

    expect($metadata->getProperty('secret')->tableName)->toBe('secret_records');
});

it('throws when encrypting a property whose metadata has no table name', function (): void {
    $cast = new EncryptedCast(new FakeEncryptor());

    $cast->toDatabase('value', new PropertyMetadata(name: 'secret', columnName: 'secret', type: 'string'));
})->throws(EncryptionException::class, "Cannot encrypt property 'secret' without a table name");
