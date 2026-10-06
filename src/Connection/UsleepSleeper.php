<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Sleeps with usleep(), blocking the current process.
 */
class UsleepSleeper implements SleeperInterface
{
    public function sleep(
        int $milliseconds,
    ): void {
        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * 1_000);
    }
}
