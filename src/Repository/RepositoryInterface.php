<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\EntityNotFoundException;
use Marko\Database\Exceptions\RepositoryException;

/**
 * Interface for entity repositories providing data access methods.
 *
 * @template TEntity of Entity
 */
interface RepositoryInterface
{
    /**
     * Find an entity by its primary key.
     *
     * @return TEntity|null The entity or null if not found
     */
    public function find(int|string $id): ?Entity;

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @return TEntity The entity
     * @throws EntityNotFoundException When entity is not found
     */
    public function findOrFail(int|string $id): Entity;

    /**
     * Find all entities in the repository.
     *
     * @return EntityCollection<TEntity> All entities
     */
    public function findAll(): EntityCollection;

    /**
     * Find entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return EntityCollection<TEntity> Matching entities
     */
    public function findBy(array $criteria): EntityCollection;

    /**
     * Find a single entity matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return TEntity|null The entity or null if not found
     */
    public function findOneBy(array $criteria): ?Entity;

    /**
     * Check if any entity matches the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     */
    public function existsBy(array $criteria): bool;

    /**
     * Save an entity (insert or update).
     *
     * @param Entity $entity The entity to save
     * @throws RepositoryException
     */
    public function save(Entity $entity): void;

    /**
     * Delete an entity.
     *
     * @param Entity $entity The entity to delete
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void;

    /**
     * Insert multiple entities in a single multi-row INSERT statement.
     *
     * This is an explicit escape hatch for import and seed operations that need
     * to persist large numbers of entities efficiently.
     *
     * **Caveats:**
     * - Relationships are NOT auto-persisted. You must persist related entities
     *   separately before or after calling this method.
     * - `EntityCreating` and `EntityCreated` lifecycle events fire synchronously
     *   for each entity. For high-throughput imports where observer work is
     *   expensive, consider marking observers async (see `marko/queue`) or
     *   dropping to the raw query builder for pure-SQL bulk inserts that bypass
     *   the entity layer entirely.
     * - The column set is derived from the first entity and enforced across all
     *   entities in the batch; mixing entities whose column sets differ will
     *   throw a `BatchInsertException`.
     * - The method opens its own transaction if none is active, ensuring all
     *   rows are rolled back on failure. If an outer transaction is already
     *   active the method participates in it — the caller is responsible for
     *   committing or rolling back.
     * - Where the connection supports `RETURNING` (PostgreSQL, MariaDB 10.5+),
     *   keys are matched to entities by the row order `RETURNING` gives back.
     *   Neither server documents that a multi-row `INSERT ... RETURNING`
     *   returns rows in `VALUES` order, but both do, and integration tests
     *   cover it on every server version CI runs, including a sequence or
     *   `auto_increment` step of 5. The row count is checked
     *   (`BatchInsertException`).
     * - On MySQL (no `RETURNING`), `@@auto_increment_increment` is read once per
     *   batch, before the INSERT, through the connection (the write connection
     *   behind `marko/database-readwrite`), and each entity gets
     *   `LAST_INSERT_ID()` + offset * increment. A value that is not a positive
     *   integer throws `BatchInsertException`. Explicit keys set on every entity
     *   are kept. A single multi-row `INSERT ... VALUES` is a "simple insert":
     *   under `innodb_autoinc_lock_mode` 0 and 1 it always gets one consecutive
     *   block; under 2 (the MySQL 8.0+ default) it does too unless a bulk insert
     *   (`INSERT ... SELECT`, `REPLACE ... SELECT`, `LOAD DATA`) runs
     *   concurrently on the same table, where ids can interleave and the
     *   arithmetic would be wrong.
     * - For a per-row guarantee, call `save()` on each entity inside
     *   `transaction()` (N round trips instead of one).
     *
     * @param array<Entity> $entities Entities to insert — must all be the same class
     * @throws BatchInsertException|RepositoryException
     */
    public function insertBatch(array $entities): void;
}
