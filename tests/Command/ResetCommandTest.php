<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\ResetCommand;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Command\Helpers;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * @param array<string> $arguments
 * @return array{exitCode: int, output: string}
 */
function executeResetCommand(
    Migrator $migrator,
    ?string $environment,
    array $arguments = ['marko', 'db:reset'],
    ?ConfirmationPrompterInterface $prompter = null,
): array {
    $command = new ResetCommand(
        migrator: $migrator,
        destructiveCommandGuard: Helpers::createDestructiveCommandGuard($environment, $prompter),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $exitCode = $command->execute(new Input($arguments), $output);

    return ['exitCode' => $exitCode, 'output' => Helpers::getOutputContent($stream)];
}

it('declares force as a value-less flag', function (): void {
    $attribute = new ReflectionClass(ResetCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['force']);
});

it('refuses production even with --force', function (?string $environment): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeResetCommand($migrator, $environment, ['marko', 'db:reset', '--force']);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain("db:reset cannot be run in the 'production' environment")
        ->and($migrator->resetCalled)->toBeFalse();
})->with(['production', null]);

it('runs in development and testing', function (string $environment): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeResetCommand($migrator, $environment);

    expect($result['exitCode'])->toBe(0)
        ->and($migrator->resetCalled)->toBeTrue();
})->with(['development', 'test']);

it(
    'refuses staging and an unknown environment without --force, naming the environment and the flag',
    function (string $environment): void {
        $migrator = Helpers::createResettingMigrator();

        $result = executeResetCommand($migrator, $environment);

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])->toContain(
                "db:reset is refused in the '$environment' environment without --force",
            )
            ->and($migrator->resetCalled)->toBeFalse();
    },
)->with(['staging', 'live']);

it('runs in staging with --force when nobody can answer', function (): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeResetCommand($migrator, 'staging', ['marko', 'db:reset', '--force']);

    expect($result['exitCode'])->toBe(0)
        ->and($migrator->resetCalled)->toBeTrue();
});

it('asks for confirmation with --force when interactive', function (bool $answer): void {
    $migrator = Helpers::createResettingMigrator();
    $prompter = new FakeConfirmationPrompter(answers: [$answer]);

    $result = executeResetCommand($migrator, 'staging', ['marko', 'db:reset', '--force'], $prompter);

    $prompter->assertAsked("db:reset rolls back every migration in the 'staging' environment. Continue?");
    expect($result['exitCode'])->toBe(0)
        ->and($migrator->resetCalled)->toBe($answer);
})->with([true, false]);
