<?php

declare(strict_types=1);

use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;

describe('ExpressionDefaultProbeException', function (): void {
    it('is a MigrationException', function (): void {
        expect(ExpressionDefaultProbeException::rejected('posts', 'expires_at', 'now() +', 'syntax error'))
            ->toBeInstanceOf(MigrationException::class);
    });

    it('names the table, column, expression and database error', function (): void {
        $exception = ExpressionDefaultProbeException::rejected(
            'posts',
            'expires_at',
            "now() + intervall '1 day'",
            'syntax error at or near "\'1 day\'"',
        );

        expect($exception->getMessage())
            ->toBe("The database rejected the default expression \"now() + intervall '1 day'\" of column "
                . "'posts.expires_at': syntax error at or near \"'1 day'\"")
            ->and($exception->getContext())->toContain('posts.expires_at');
    });

    it('suggests fixing the expression or granting the temporary table privilege', function (): void {
        $suggestion = ExpressionDefaultProbeException::rejected('posts', 'expires_at', 'now() +', 'denied')
            ->getSuggestion();

        expect($suggestion)->toContain('new Expression(...)')
            ->and($suggestion)->toContain('CREATE TEMPORARY TABLES')
            ->and($suggestion)->toContain('TEMPORARY');
    });
});
