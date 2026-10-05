<?php

declare(strict_types=1);

namespace Marko\Database\Command;

/**
 * Reads a yes/no answer from the person running a command.
 *
 * The command writes the question through its own Output; the prompter only reads the answer.
 */
interface ConfirmationPrompterInterface
{
    /**
     * Whether a person can answer, i.e. input is attached to a terminal.
     */
    public function isInteractive(): bool;

    /**
     * Read one answer. Only "y" or "yes" (any case) confirm; anything else, including no input, declines.
     */
    public function confirm(): bool;
}
