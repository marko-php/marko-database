<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\RebuildCommand;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Command\Helpers;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * @param array<string> $arguments
 * @return array{exitCode: int, output: string}
 */
function executeRebuildCommand(
    Migrator $migrator,
    ?string $environment,
    array $arguments = ['marko', 'db:rebuild'],
    ?ConfirmationPrompterInterface $prompter = null,
): array {
    $command = new RebuildCommand(
        migrator: $migrator,
        destructiveCommandGuard: Helpers::createDestructiveCommandGuard($environment, $prompter),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $exitCode = $command->execute(new Input($arguments), $output);

    return ['exitCode' => $exitCode, 'output' => Helpers::getOutputContent($stream)];
}

it('declares force as a value-less flag', function (): void {
    $attribute = new ReflectionClass(RebuildCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['force']);
});

it('refuses production even with --force', function (?string $environment): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, $environment, ['marko', 'db:rebuild', '--force']);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain("db:rebuild cannot be run in the 'production' environment")
        ->and($migrator->resetCalled)->toBeFalse()
        ->and($migrator->migrateCalled)->toBeFalse();
})->with(['production', null]);

it('runs in development and testing', function (string $environment): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, $environment);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('Database rebuilt successfully.')
        ->and($migrator->resetCalled)->toBeTrue()
        ->and($migrator->migrateCalled)->toBeTrue();
})->with(['local', 'testing']);

it(
    'refuses staging and an unknown environment without --force, naming the environment and the flag',
    function (string $environment): void {
        $migrator = Helpers::createResettingMigrator();

        $result = executeRebuildCommand($migrator, $environment);

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])->toContain(
                "db:rebuild is refused in the '$environment' environment without --force",
            )
            ->and($migrator->resetCalled)->toBeFalse();
    },
)->with(['staging', 'prodution']);

it('runs in staging with --force when nobody can answer', function (): void {
    $migrator = Helpers::createResettingMigrator();

    $result = executeRebuildCommand($migrator, 'staging', ['marko', 'db:rebuild', '--force']);

    expect($result['exitCode'])->toBe(0)
        ->and($migrator->resetCalled)->toBeTrue()
        ->and($migrator->migrateCalled)->toBeTrue();
});

it('asks for confirmation with --force when interactive', function (bool $answer): void {
    $migrator = Helpers::createResettingMigrator();
    $prompter = new FakeConfirmationPrompter(answers: [$answer]);

    $result = executeRebuildCommand($migrator, 'staging', ['marko', 'db:rebuild', '--force'], $prompter);

    $prompter->assertAsked(
        "db:rebuild drops every table and re-runs all migrations in the 'staging' environment. Continue?",
    );
    expect($result['exitCode'])->toBe(0)
        ->and($migrator->resetCalled)->toBe($answer);
})->with([true, false]);
