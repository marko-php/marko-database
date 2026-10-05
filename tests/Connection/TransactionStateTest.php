<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionState;

describe('TransactionState', function (): void {
    it('starts at level zero', function (): void {
        expect((new TransactionState())->level())->toBe(0);
    });

    it('increments the level on begin and decrements on commit and rollback', function (): void {
        $state = new TransactionState();

        $state->begin();
        $state->begin();
        $afterTwoBegins = $state->level();
        $state->commit();
        $afterCommit = $state->level();
        $state->rollback();

        expect($afterTwoBegins)->toBe(2)
            ->and($afterCommit)->toBe(1)
            ->and($state->level())->toBe(0);
    });

    it('runs an after-commit callback immediately at level zero', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->afterCommit(function () use (&$ran): void {
            $ran = true;
        });

        expect($ran)->toBeTrue();
    });

    it('runs after-commit callbacks only after the outermost commit', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use (&$log, $state): void {
            $log[] = 'outer@' . $state->level();
        });
        $state->begin();
        $state->afterCommit(function () use (&$log, $state): void {
            $log[] = 'inner@' . $state->level();
        });
        $state->commit();
        $afterInnerCommit = $log;
        $state->commit();

        expect($afterInnerCommit)->toBe([])
            ->and($log)->toBe(['outer@0', 'inner@0']);
    });

    it('merges inner after-commit callbacks into the parent level on inner commit', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'inner';
        });
        $state->commit();
        $state->rollback();

        expect($log)->toBe([]);
    });

    it('discards after-commit callbacks of a rolled-back level', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'outer';
        });
        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'inner';
        });
        $state->rollback();
        $state->commit();

        expect($log)->toBe(['outer']);
    });

    it('runs after-rollback callbacks of a rolled-back level', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterRollback(function () use (&$log): void {
            $log[] = 'outer';
        });
        $state->begin();
        $state->afterRollback(function () use (&$log, $state): void {
            $log[] = 'inner@' . $state->level();
        });
        $state->rollback();
        $afterInnerRollback = $log;
        $state->commit();

        expect($afterInnerRollback)->toBe(['inner@1'])
            ->and($log)->toBe(['inner@1']);
    });

    it(
        'runs after-rollback callbacks merged from a committed inner level when the outer level rolls back',
        function (): void {
            $state = new TransactionState();
            $log = [];

            $state->begin();
            $state->begin();
            $state->afterRollback(function () use (&$log): void {
                $log[] = 'inner';
            });
            $state->commit();
            $state->rollback();

            expect($log)->toBe(['inner']);
        },
    );

    it('ignores after-rollback callbacks registered at level zero', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->begin();
        $state->rollback();

        expect($ran)->toBeFalse();
    });

    it('propagates an exception thrown by a callback', function (): void {
        $state = new TransactionState();
        $state->begin();
        $state->afterCommit(function (): void {
            throw new RuntimeException('Mail server down');
        });

        expect(fn () => $state->commit())->toThrow(RuntimeException::class, 'Mail server down')
            ->and($state->level())->toBe(0);
    });

    it('clears every level without running callbacks', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->begin();
        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->begin();
        $state->clear();

        expect($state->level())->toBe(0)
            ->and($ran)->toBeFalse();
    });

    it('drops the current level without running its callbacks on discard', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->begin();
        $state->begin();
        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->discard();

        expect($state->level())->toBe(1)
            ->and($ran)->toBeFalse();
    });
});
