<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;

describe('DatabaseTimezoneConfig format and parse', function (): void {
    it('formats an instant in the database timezone whatever its own timezone', function (): void {
        $config = DatabaseTimezoneConfig::fromName('UTC');
        $instant = new DateTimeImmutable('2026-03-14 11:09:26', new DateTimeZone('America/New_York'));

        expect($config->format($instant))->toBe('2026-03-14 15:09:26');
    });

    it('formats in a non-UTC database timezone', function (): void {
        $config = DatabaseTimezoneConfig::fromName('Europe/Berlin');
        $instant = new DateTimeImmutable('2026-03-14 15:09:26', new DateTimeZone('UTC'));

        expect($config->format($instant))->toBe('2026-03-14 16:09:26');
    });

    it('parses a stored string as a time in the database timezone', function (): void {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $parsed = DatabaseTimezoneConfig::fromName('UTC')->parse('2026-03-14 15:09:26');
        } finally {
            date_default_timezone_set($previous);
        }

        expect($parsed->getTimezone()->getName())->toBe('UTC')
            ->and($parsed->getTimestamp())->toBe(new DateTimeImmutable('2026-03-14T15:09:26Z')->getTimestamp());
    });

    it('round-trips an instant through format and parse', function (): void {
        $config = DatabaseTimezoneConfig::fromName('Asia/Tokyo');
        $instant = new DateTimeImmutable('2026-11-01 01:50:00', new DateTimeZone('America/New_York'));

        expect($config->parse($config->format($instant))->getTimestamp())->toBe($instant->getTimestamp());
    });

    it('throws when parsing a malformed stored string', function (): void {
        DatabaseTimezoneConfig::fromName('UTC')->parse('not a timestamp');
    })->throws(DateMalformedStringException::class);
});
