<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Pauses the current process. TransactionBackoff waits through it between
 * transaction() retry attempts, so tests can record the delays instead of
 * sleeping (FakeSleeper in marko/testing).
 */
interface SleeperInterface
{
    /**
     * Pause for the given number of milliseconds (0 returns at once).
     */
    public function sleep(int $milliseconds): void;
}
