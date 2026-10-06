<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Implemented by connections that can send reads somewhere other than the
 * primary database, such as a read/write split that serves SELECTs from
 * replicas.
 *
 * A read whose answer must never be stale (a session lookup after logout, a
 * "token already used" check) runs inside onPrimary(), so it is served by the
 * primary rather than a replica that may lag behind the last write. A
 * connection that does not implement this interface always reads from its
 * only database, so callers run the read directly:
 *
 *     $rows = $connection instanceof PrimaryReadInterface
 *         ? $connection->onPrimary(fn (): array => $connection->query($sql, $bindings))
 *         : $connection->query($sql, $bindings);
 */
interface PrimaryReadInterface
{
    /**
     * Run the callback with every read on this connection routed to the
     * primary, and return what the callback returns.
     *
     * Reads after the callback go back to the routing they had before, unless
     * the callback wrote something: then later reads stay on the primary so
     * they see the write.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function onPrimary(
        callable $callback,
    ): mixed;
}
