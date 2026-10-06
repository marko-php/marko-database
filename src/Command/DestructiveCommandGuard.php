<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Environment\AppEnvironment;

/**
 * Decides whether a destructive database command (db:rebuild, db:reset, db:rollback, db:seed)
 * may run in the current environment.
 *
 * Destructive commands need evidence that the database is disposable, not merely the absence
 * of the word "production":
 *
 * - development and testing: allowed
 * - production (or no environment set): refused, even with --force
 * - anything else (staging, qa, a typo, ...): refused unless --force is passed; with --force,
 *   the command still asks for confirmation when someone can answer
 */
readonly class DestructiveCommandGuard
{
    public function __construct(
        private AppEnvironment $appEnvironment,
        private ConfirmationPrompterInterface $confirmationPrompter,
    ) {}

    /**
     * Returns null when the command may go ahead, or the exit code to stop with:
     * 1 when the environment refuses it, 0 when the user declines the confirmation.
     *
     * @param string $command The command name, e.g. "db:rebuild"
     * @param string $effect What the command does to the database, e.g. "drops every table"
     */
    public function check(
        string $command,
        string $effect,
        Input $input,
        Output $output,
    ): ?int {
        $environment = $this->appEnvironment->name();

        if ($this->appEnvironment->isProduction()) {
            $output->writeLine("Error: $command cannot be run in the '$environment' environment.");
            $output->writeLine("This command $effect and is never allowed in production, even with --force.");

            return 1;
        }

        if ($this->appEnvironment->isDevelopment() || $this->appEnvironment->isTesting()) {
            return null;
        }

        if (!$input->hasOption('force')) {
            $output->writeLine("Error: $command is refused in the '$environment' environment without --force.");
            $output->writeLine(
                "This command $effect. It runs without --force only in development "
                . '(development, dev, local) and testing (testing, test).',
            );
            $output->writeLine("Re-run with --force if the '$environment' database may be changed.");

            return 1;
        }

        if (!$this->confirmationPrompter->isInteractive()) {
            return null;
        }

        if ($this->confirmationPrompter->confirm("$command $effect in the '$environment' environment. Continue?")) {
            return null;
        }

        $output->writeLine("$command cancelled.");

        return 0;
    }
}
