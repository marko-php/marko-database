<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Environment\AppEnvironment;
use Marko\Database\Command\RebuildCommand;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Command\Helpers;

/**
 * @param array<string, string> $variables
 * @return array{exitCode: int, output: string}
 */
function executeRebuildCommand(
    Migrator $migrator,
    array $variables,
): array {
    $command = new RebuildCommand(
        migrator: $migrator,
        appEnvironment: new AppEnvironment($variables),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $exitCode = $command->execute(new Input(['marko', 'db:rebuild']), $output);

    return ['exitCode' => $exitCode, 'output' => Helpers::getOutputContent($stream)];
}

it('refuses to rebuild and exits 1 when APP_ENV is production', function (): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, ['APP_ENV' => 'production']);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain('Rebuild cannot be run in production')
        ->and($migrator->resetCalled)->toBeFalse()
        ->and($migrator->migrateCalled)->toBeFalse();
});

it('refuses to rebuild and exits 1 when APP_ENV is unset', function (): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, []);

    expect($result['exitCode'])->toBe(1)
        ->and($migrator->resetCalled)->toBeFalse();
});

it('rebuilds the database in development', function (): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, ['APP_ENV' => 'local']);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('Database rebuilt successfully.')
        ->and($migrator->resetCalled)->toBeTrue()
        ->and($migrator->migrateCalled)->toBeTrue();
});
