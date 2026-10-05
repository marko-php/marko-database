<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Environment\AppEnvironment;
use Marko\Database\Command\ResetCommand;
use Marko\Database\Tests\Command\Helpers;

it('refuses to reset and exits 1 in production', function (): void {
    $migrator = Helpers::createResettingMigrator();
    $command = new ResetCommand(
        migrator: $migrator,
        appEnvironment: new AppEnvironment(['APP_ENV' => 'production']),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $exitCode = $command->execute(new Input(['marko', 'db:reset']), $output);

    expect($exitCode)->toBe(1)
        ->and(Helpers::getOutputContent($stream))->toContain('Reset cannot be run in production')
        ->and($migrator->resetCalled)->toBeFalse();
});

it('resets the database in development', function (): void {
    $migrator = Helpers::createResettingMigrator();
    $command = new ResetCommand(
        migrator: $migrator,
        appEnvironment: new AppEnvironment(['APP_ENV' => 'development']),
    );
    ['output' => $output] = Helpers::createOutputStream();

    $exitCode = $command->execute(new Input(['marko', 'db:reset']), $output);

    expect($exitCode)->toBe(0)
        ->and($migrator->resetCalled)->toBeTrue();
});
