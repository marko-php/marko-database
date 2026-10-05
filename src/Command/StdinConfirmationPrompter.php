<?php

declare(strict_types=1);

namespace Marko\Database\Command;

/**
 * Reads confirmation answers from standard input.
 */
readonly class StdinConfirmationPrompter implements ConfirmationPrompterInterface
{
    /**
     * @param resource|null $stream Input stream to read from; standard input when null
     */
    public function __construct(
        private mixed $stream = null,
    ) {}

    public function isInteractive(): bool
    {
        return stream_isatty($this->stream());
    }

    public function confirm(): bool
    {
        $line = fgets($this->stream());
        $answer = strtolower(trim($line !== false ? $line : ''));

        return $answer === 'y' || $answer === 'yes';
    }

    /**
     * @return resource
     */
    private function stream(): mixed
    {
        return $this->stream ?? STDIN;
    }
}
