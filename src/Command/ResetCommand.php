<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\Migrator;

/** @noinspection PhpUnused */
#[Command(name: 'db:reset', description: 'Rollback all database migrations', flags: ['force'])]
readonly class ResetCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private DestructiveCommandGuard $destructiveCommandGuard,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $refusal = $this->destructiveCommandGuard->check('db:reset', 'rolls back every migration', $input, $output);

        if ($refusal !== null) {
            return $refusal;
        }

        try {
            $output->writeLine('Rolling back all migrations...');
            $rolledBack = $this->migrator->reset();

            foreach ($rolledBack as $migration) {
                $output->writeLine("  Rolled back: $migration");
            }

            if ($rolledBack === []) {
                $output->writeLine('Nothing to rollback.');

                return 0;
            }

            $count = count($rolledBack);
            $output->writeLine('');
            $output->writeLine("Reset $count migration(s) successfully.");

            return 0;
        } catch (MigrationException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
