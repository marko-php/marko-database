<?php

declare(strict_types=1);

use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Environment\AppEnvironment;
use Marko\Database\Command\DestructiveCommandGuard;
use Marko\Database\Tests\Command\Helpers;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * @param array<string> $arguments
 * @return array{result: ?int, output: string}
 */
function checkDestructiveCommand(
    string $environment,
    array $arguments = ['marko', 'db:rebuild'],
    ?ConfirmationPrompterInterface $prompter = null,
): array {
    $guard = new DestructiveCommandGuard(
        appEnvironment: new AppEnvironment(['APP_ENV' => $environment]),
        confirmationPrompter: $prompter ?? new FakeConfirmationPrompter(interactive: false),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $result = $guard->check('db:rebuild', 'drops every table', new Input($arguments), $output);

    return ['result' => $result, 'output' => Helpers::getOutputContent($stream)];
}

it('allows development and testing environments without asking', function (string $environment): void {
    $prompter = new FakeConfirmationPrompter();

    $check = checkDestructiveCommand($environment, prompter: $prompter);

    expect($check['result'])->toBeNull()
        ->and($check['output'])->toBe('');
    $prompter->assertNothingAsked();
})->with(['development', 'dev', 'local', 'testing', 'test']);

it('refuses production even with --force', function (string $environment): void {
    $prompter = new FakeConfirmationPrompter();

    $check = checkDestructiveCommand($environment, ['marko', 'db:rebuild', '--force'], $prompter);

    expect($check['result'])->toBe(1)
        ->and($check['output'])->toContain("Error: db:rebuild cannot be run in the '$environment' environment.")
        ->and($check['output'])->toContain('never allowed in production, even with --force');
    $prompter->assertNothingAsked();
})->with(['production', 'prod']);

it('refuses production when no environment is set', function (): void {
    $guard = new DestructiveCommandGuard(
        appEnvironment: new AppEnvironment([]),
        confirmationPrompter: new FakeConfirmationPrompter(interactive: false),
    );
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $result = $guard->check('db:rebuild', 'drops every table', new Input(['marko', 'db:rebuild', '--force']), $output);

    expect($result)->toBe(1)
        ->and(Helpers::getOutputContent($stream))->toContain("'production' environment");
});

it(
    'refuses staging and unknown environments without --force, naming the environment and the flag',
    function (string $environment): void {
        $check = checkDestructiveCommand($environment);

        expect($check['result'])->toBe(1)
            ->and($check['output'])->toContain(
                "Error: db:rebuild is refused in the '$environment' environment without --force.",
            )
            ->and($check['output'])->toContain('drops every table')
            ->and($check['output'])->toContain('Re-run with --force');
    },
)->with(['staging', 'qa', 'prodution']);

it('allows a non-production environment with --force when nobody can answer', function (): void {
    $prompter = new FakeConfirmationPrompter(interactive: false);

    $check = checkDestructiveCommand('staging', ['marko', 'db:rebuild', '--force'], $prompter);

    expect($check['result'])->toBeNull();
    $prompter->assertNothingAsked();
});

it('asks for confirmation with --force when interactive and proceeds on yes', function (): void {
    $prompter = new FakeConfirmationPrompter(answers: [true]);

    $check = checkDestructiveCommand('staging', ['marko', 'db:rebuild', '--force'], $prompter);

    expect($check['result'])->toBeNull();
    $prompter->assertAsked("db:rebuild drops every table in the 'staging' environment. Continue?");
});

it('cancels with exit code 0 when the confirmation is declined', function (): void {
    $prompter = new FakeConfirmationPrompter(answers: [false]);

    $check = checkDestructiveCommand('staging', ['marko', 'db:rebuild', '--force'], $prompter);

    expect($check['result'])->toBe(0)
        ->and($check['output'])->toContain('db:rebuild cancelled.');
});

it('does not ask without --force even when interactive', function (): void {
    $prompter = new FakeConfirmationPrompter();

    $check = checkDestructiveCommand('staging', prompter: $prompter);

    expect($check['result'])->toBe(1);
    $prompter->assertNothingAsked();
});

/**
 * Run the guard with the opt-ins a production-safe command passes (allowed in production with --force,
 * confirmed in development).
 *
 * @param array<string> $arguments
 * @return array{result: ?int, output: string}
 */
function checkOptedInDestructiveCommand(
    ?string $environment,
    array $arguments,
    ConfirmationPrompterInterface $prompter,
    bool $allowInProduction = true,
): array {
    $guard = Helpers::createDestructiveCommandGuard($environment, $prompter);
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

    $result = $guard->check(
        'perms:prune',
        'deletes stale permissions',
        new Input($arguments),
        $output,
        allowInProduction: $allowInProduction,
        confirmInDevelopment: true,
    );

    return ['result' => $result, 'output' => Helpers::getOutputContent($stream)];
}

it('refuses production without --force when production is allowed', function (string $environment): void {
    $prompter = new FakeConfirmationPrompter();

    $check = checkOptedInDestructiveCommand($environment, ['marko', 'perms:prune'], $prompter);

    expect($check['result'])->toBe(1)
        ->and($check['output'])->toContain(
            "Error: perms:prune is refused in the '$environment' environment without --force.",
        )
        ->and($check['output'])->toContain('Re-run with --force')
        ->and($check['output'])->not->toContain('never allowed in production');
    $prompter->assertNothingAsked();
})->with(['production', 'prod']);

it('runs in production with --force when production is allowed and nobody can answer', function (): void {
    $prompter = new FakeConfirmationPrompter(interactive: false);

    $check = checkOptedInDestructiveCommand('production', ['marko', 'perms:prune', '--force'], $prompter);

    expect($check['result'])->toBeNull()
        ->and($check['output'])->toBe('');
});

it(
    'asks for confirmation in production with --force when production is allowed and interactive',
    function (): void {
        $prompter = new FakeConfirmationPrompter(answers: [false]);

        $check = checkOptedInDestructiveCommand('production', ['marko', 'perms:prune', '--force'], $prompter);

        expect($check['result'])->toBe(0)
            ->and($check['output'])->toContain('perms:prune cancelled.');
        $prompter->assertAsked("perms:prune deletes stale permissions in the 'production' environment. Continue?");
    },
);

it('treats an unset environment as production when production is allowed', function (): void {
    $prompter = new FakeConfirmationPrompter(interactive: false);

    $refused = checkOptedInDestructiveCommand(null, ['marko', 'perms:prune'], $prompter);
    $forced = checkOptedInDestructiveCommand(null, ['marko', 'perms:prune', '--force'], $prompter);

    expect($refused['result'])->toBe(1)
        ->and($refused['output'])->toContain("'production' environment without --force")
        ->and($forced['result'])->toBeNull();
});

it(
    'asks for confirmation in development when confirmInDevelopment is set and interactive',
    function (string $environment): void {
        $prompter = new FakeConfirmationPrompter(answers: [true]);

        $check = checkOptedInDestructiveCommand($environment, ['marko', 'perms:prune'], $prompter);

        expect($check['result'])->toBeNull();
        $prompter->assertAsked("perms:prune deletes stale permissions in the '$environment' environment. Continue?");
    },
)->with(['development', 'testing']);

it(
    'runs in development without asking when confirmInDevelopment is set and nobody can answer',
    function (): void {
        $prompter = new FakeConfirmationPrompter(interactive: false);

        $check = checkOptedInDestructiveCommand('development', ['marko', 'perms:prune'], $prompter);

        expect($check['result'])->toBeNull()
            ->and($check['output'])->toBe('');
    },
);

it('cancels in development when confirmInDevelopment is set and the answer is no', function (): void {
    $prompter = new FakeConfirmationPrompter(answers: [false]);

    $check = checkOptedInDestructiveCommand('local', ['marko', 'perms:prune'], $prompter);

    expect($check['result'])->toBe(0)
        ->and($check['output'])->toContain('perms:prune cancelled.');
});

it('still refuses production with --force when only confirmInDevelopment is set', function (): void {
    $prompter = new FakeConfirmationPrompter();

    $check = checkOptedInDestructiveCommand(
        'production',
        ['marko', 'perms:prune', '--force'],
        $prompter,
        allowInProduction: false,
    );

    expect($check['result'])->toBe(1)
        ->and($check['output'])->toContain('never allowed in production, even with --force');
    $prompter->assertNothingAsked();
});
