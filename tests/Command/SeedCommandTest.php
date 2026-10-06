<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Command\DestructiveCommandGuard;
use Marko\Database\Command\SeedCommand;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscoveryInterface;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;
use Marko\Database\Tests\Command\Helpers;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * Helper to create a stub SeederDiscovery.
 *
 * @param array<SeederDefinition> $vendorDefinitions
 * @param array<SeederDefinition> $modulesDefinitions
 * @param array<SeederDefinition> $appDefinitions
 */
function createStubDiscovery(
    array $vendorDefinitions = [],
    array $modulesDefinitions = [],
    array $appDefinitions = [],
): SeederDiscoveryInterface {
    return new readonly class ($vendorDefinitions, $modulesDefinitions, $appDefinitions) implements SeederDiscoveryInterface
    {
        public function __construct(
            private array $vendorDefs,
            private array $modulesDefs,
            private array $appDefs,
        ) {}

        public function discoverInVendor(
            string $vendorPath,
        ): array {
            return $this->vendorDefs;
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            return $this->modulesDefs;
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            return $this->appDefs;
        }
    };
}

/**
 * Helper to create a no-op seeder for testing.
 */
function createNoOpSeeder(): SeederInterface
{
    return new class () implements SeederInterface
    {
        public function run(): void {}
    };
}

/**
 * Helper to create a SeedCommand with standard dependencies.
 *
 * @param array<SeederDefinition> $definitions
 * @param array<string, SeederInterface> $seeders
 */
function createSeedCommand(
    array $definitions = [],
    array $seeders = [],
    ?string $appEnv = 'local',
    ?ConfirmationPrompterInterface $prompter = null,
    ?SeederDiscoveryInterface $discovery = null,
): SeedCommand {
    $appEnvironment = new AppEnvironment($appEnv === null ? [] : ['APP_ENV' => $appEnv]);
    $discovery ??= createStubDiscovery(vendorDefinitions: $definitions);

    $runner = new SeederRunner(
        seeders: $seeders,
        appEnvironment: $appEnvironment,
    );

    return new SeedCommand(
        discovery: $discovery,
        runner: $runner,
        paths: new ProjectPaths('/test'),
        destructiveCommandGuard: new DestructiveCommandGuard(
            appEnvironment: $appEnvironment,
            confirmationPrompter: $prompter ?? new FakeConfirmationPrompter(interactive: false),
        ),
    );
}

/**
 * Helper to execute a SeedCommand and return the output.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeSeedCommand(
    SeedCommand $command,
    array $args = ['marko', 'db:seed'],
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as db:seed command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:seed');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(SeedCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('discovers all seeders from modules', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('users')
        ->and($output)->toContain('posts');
});

it('runs seeders in specified order', function (): void {
    $executionOrder = [];

    $seeder1 = new class ($executionOrder) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$order,
        ) {}

        public function run(): void
        {
            $this->order[] = 'second';
        }
    };

    $seeder2 = new class ($executionOrder) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$order,
        ) {}

        public function run(): void
        {
            $this->order[] = 'first';
        }
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder1), name: 'second', order: 20),
        new SeederDefinition(seederClass: get_class($seeder2), name: 'first', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [
            get_class($seeder1) => $seeder1,
            get_class($seeder2) => $seeder2,
        ],
    );

    executeSeedCommand($command);

    expect($executionOrder)->toBe(['first', 'second']);
});

it('shows each seeder being run', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($seeder), name: 'posts', order: 20),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('Running seeder: users')
        ->and($output)->toContain('Running seeder: posts');
});

it('supports --class option to run specific seeder', function (): void {
    $userSeederRan = false;
    $postSeederRan = false;

    $userSeeder = new class ($userSeederRan) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private bool &$ran,
        ) {}

        public function run(): void
        {
            $this->ran = true;
        }
    };

    $postSeeder = new class ($postSeederRan) implements SeederInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private bool &$ran,
        ) {}

        public function run(): void
        {
            $this->ran = true;
        }
    };

    $definitions = [
        new SeederDefinition(seederClass: get_class($userSeeder), name: 'users', order: 10),
        new SeederDefinition(seederClass: get_class($postSeeder), name: 'posts', order: 20),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [
            get_class($userSeeder) => $userSeeder,
            get_class($postSeeder) => $postSeeder,
        ],
    );

    executeSeedCommand($command, ['marko', 'db:seed', '--class=users']);

    expect($userSeederRan)->toBeTrue()
        ->and($postSeederRan)->toBeFalse();
});

it('accepts --class Name as well as --class=Name for db:seed', function (): void {
    $seeder = createNoOpSeeder();

    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
    );

    ['output' => $output] = executeSeedCommand($command, ['marko', 'db:seed', '--class', 'users']);

    expect($output)->toContain('Running seeder: users');
});

it('blocks execution in production environment', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'production',
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(1);
});

it('refuses to seed and exits 1 when APP_ENV is unset', function (): void {
    $seeder = createNoOpSeeder();

    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
        appEnv: null,
    );

    ['output' => $output, 'exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("db:seed cannot be run in the 'production' environment");
});

it('shows error message when blocked in production', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'production',
    );

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain("db:seed cannot be run in the 'production' environment");
});

it('refuses production even with --force', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'production',
    );

    // Even with --force, it should still block
    ['output' => $output, 'exitCode' => $exitCode] = executeSeedCommand(
        $command,
        ['marko', 'db:seed', '--force'],
    );

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("db:seed cannot be run in the 'production' environment");
});

it('shows "No seeders found" when none discovered', function (): void {
    $command = createSeedCommand();

    ['output' => $output] = executeSeedCommand($command);

    expect($output)->toContain('No seeders found');
});

it('returns 0 on success, 1 on failure', function (): void {
    $seeder = createNoOpSeeder();

    $definitions = [
        new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10),
    ];

    $command = createSeedCommand(
        definitions: $definitions,
        seeders: [get_class($seeder) => $seeder],
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(0);
});

/**
 * A seeder that counts its runs in a shared counter object.
 *
 * @return array{seeder: SeederInterface, ran: object{count: int}}
 */
function createCountingSeeder(): array
{
    $ran = new class ()
    {
        public int $count = 0;
    };

    return [
        'seeder' => new readonly class ($ran) implements SeederInterface
        {
            public function __construct(
                private object $ran,
            ) {}

            public function run(): void
            {
                $this->ran->count++;
            }
        },
        'ran' => $ran,
    ];
}

it('declares force as a value-less flag', function (): void {
    $attribute = new ReflectionClass(SeedCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['force']);
});

it('seeds in development and testing', function (string $environment): void {
    ['seeder' => $seeder, 'ran' => $ran] = createCountingSeeder();
    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
        appEnv: $environment,
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(0)
        ->and($ran->count)->toBe(1);
})->with(['local', 'testing', 'test']);

it(
    'refuses staging and an unknown environment without --force, naming the environment and the flag',
    function (string $environment): void {
        ['seeder' => $seeder, 'ran' => $ran] = createCountingSeeder();
        $command = createSeedCommand(
            definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
            seeders: [get_class($seeder) => $seeder],
            appEnv: $environment,
        );

        ['output' => $output, 'exitCode' => $exitCode] = executeSeedCommand($command);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain("db:seed is refused in the '$environment' environment without --force")
            ->and($ran->count)->toBe(0);
    },
)->with(['staging', 'demo']);

it('seeds in staging with --force when nobody can answer', function (): void {
    ['seeder' => $seeder, 'ran' => $ran] = createCountingSeeder();
    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'staging',
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command, ['marko', 'db:seed', '--force']);

    expect($exitCode)->toBe(0)
        ->and($ran->count)->toBe(1);
});

it('forwards --force to the runner for a single --class seeder in staging', function (): void {
    ['seeder' => $seeder, 'ran' => $ran] = createCountingSeeder();
    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'staging',
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command, ['marko', 'db:seed', '--force', '--class', 'users']);

    expect($exitCode)->toBe(0)
        ->and($ran->count)->toBe(1);
});

it('asks for confirmation with --force when interactive', function (bool $answer): void {
    ['seeder' => $seeder, 'ran' => $ran] = createCountingSeeder();
    $prompter = new FakeConfirmationPrompter(answers: [$answer]);
    $command = createSeedCommand(
        definitions: [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 10)],
        seeders: [get_class($seeder) => $seeder],
        appEnv: 'staging',
        prompter: $prompter,
    );

    ['exitCode' => $exitCode] = executeSeedCommand($command, ['marko', 'db:seed', '--force']);

    $prompter->assertAsked("db:seed writes seed data to the database in the 'staging' environment. Continue?");
    expect($exitCode)->toBe(0)
        ->and($ran->count)->toBe($answer ? 1 : 0);
})->with([true, false]);

it('does not discover seeders when refused', function (): void {
    $discovery = new class () implements SeederDiscoveryInterface
    {
        public int $calls = 0;

        public function discoverInVendor(
            string $vendorPath,
        ): array {
            $this->calls++;

            return [];
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            $this->calls++;

            return [];
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            $this->calls++;

            return [];
        }
    };
    $command = createSeedCommand(appEnv: 'staging', discovery: $discovery);

    ['exitCode' => $exitCode] = executeSeedCommand($command);

    expect($exitCode)->toBe(1)
        ->and($discovery->calls)->toBe(0);
});
