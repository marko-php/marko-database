<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Implemented by transaction-capable connections that can run their queued
 * after-commit callbacks without committing.
 *
 * Tests that wrap each case in a transaction that is always rolled back
 * (Marko\Testing\Database\RefreshDatabase) use it to assert on work deferred
 * with TransactionInterface::afterCommit(). Production code should not call it:
 * the callbacks would run while the data is still uncommitted.
 */
interface PendingAfterCommitInterface
{
    /**
     * Run the after-commit callbacks queued at every open transaction level,
     * outermost level first, as though the outermost transaction had committed,
     * and forget them. Nothing is committed and no level is closed.
     */
    public function runPendingAfterCommitCallbacks(): void;
}
