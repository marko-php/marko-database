<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use InvalidArgumentException;
use Marko\Database\Schema\IdentifierName;

describe('IdentifierName', function (): void {
    it('returns a derived name that fits unchanged', function (): void {
        expect(IdentifierName::derive('users_email', suffix: '_unique'))->toBe('users_email_unique')
            ->and(IdentifierName::derive('users_team_id', prefix: 'fk_'))->toBe('fk_users_team_id');
    });

    it('returns a derived name of exactly 63 bytes unchanged', function (): void {
        $body = str_repeat('a', 56);

        expect(IdentifierName::derive($body, suffix: '_unique'))->toBe($body . '_unique');
    });

    it('shortens a derived name over 63 bytes to at most 63 bytes keeping the prefix and suffix', function (): void {
        $body = 'customer_subscription_events_external_billing_reference_id';
        $name = IdentifierName::derive($body, suffix: '_unique');
        $foreignKey = IdentifierName::derive($body . '_long', prefix: 'fk_');

        expect(strlen($name))->toBeLessThanOrEqual(63)
            ->and($name)->toStartWith('customer_subscription_events_external_bil')
            ->and($name)->toMatch('/_[0-9a-f]{8}_unique$/')
            ->and(strlen($foreignKey))->toBeLessThanOrEqual(63)
            ->and($foreignKey)->toStartWith('fk_customer_subscription_events_')
            ->and($foreignKey)->toMatch('/_[0-9a-f]{8}$/');
    });

    it('appends the crc32b hash of the full name', function (): void {
        $body = 'customer_subscription_events_external_billing_reference_id';

        expect(IdentifierName::derive($body, suffix: '_unique'))
            ->toEndWith('_' . hash('crc32b', $body . '_unique') . '_unique');
    });

    it('derives a known literal name for a known long input', function (): void {
        // Pinned: changing the scheme would rename every shortened index and foreign key in existing databases
        expect(IdentifierName::derive(
            'customer_subscription_events_external_billing_reference_id',
            suffix: '_unique',
        ))->toBe('customer_subscription_events_external_billing_r_5e024629_unique');
    });

    it('throws when the prefix and suffix leave no room for the body', function (): void {
        expect(fn () => IdentifierName::derive(str_repeat('b', 10), suffix: '_' . str_repeat('s', 60)))
            ->toThrow(InvalidArgumentException::class);
    });

    it('derives the same shortened name for the same input', function (): void {
        $body = str_repeat('long_table_name_', 6) . 'column';

        expect(IdentifierName::derive($body, suffix: '_unique'))
            ->toBe(IdentifierName::derive($body, suffix: '_unique'));
    });

    it('derives different names for long inputs that share their first 63 bytes', function (): void {
        $shared = str_repeat('x', 70);

        expect(IdentifierName::derive($shared . '_first', suffix: '_unique'))
            ->not->toBe(IdentifierName::derive($shared . '_second', suffix: '_unique'));
    });

    it('cuts a multibyte body without splitting a character', function (): void {
        $name = IdentifierName::derive(str_repeat('é', 40), suffix: '_unique');

        expect(strlen($name))->toBeLessThanOrEqual(63)
            ->and(mb_check_encoding($name, 'UTF-8'))->toBeTrue();
    });

    it('measures identifier length in bytes', function (): void {
        expect(IdentifierName::byteLength('idx_é'))->toBe(6)
            ->and(IdentifierName::fits(str_repeat('é', 31)))->toBeTrue()
            ->and(IdentifierName::fits(str_repeat('é', 32)))->toBeFalse();
    });
});
