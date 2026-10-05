<?php

declare(strict_types=1);

use Marko\Database\Command\ConfirmationPrompterInterface;
use Marko\Database\Command\StdinConfirmationPrompter;

/**
 * @return resource
 */
function promptInputStream(
    string $content,
): mixed {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

it('implements ConfirmationPrompterInterface', function (): void {
    expect(new StdinConfirmationPrompter(promptInputStream('')))->toBeInstanceOf(ConfirmationPrompterInterface::class);
});

it('confirms on y or yes in any case', function (string $answer): void {
    expect(new StdinConfirmationPrompter(promptInputStream("$answer\n"))->confirm())->toBeTrue();
})->with(['y', 'yes', 'Y', ' YES ']);

it('declines on any other answer or no input', function (string $answer): void {
    expect(new StdinConfirmationPrompter(promptInputStream($answer))->confirm())->toBeFalse();
})->with(["n\n", "no\n", "\n", 'maybe', '']);

it('is not interactive when input is not a terminal', function (): void {
    expect(new StdinConfirmationPrompter(promptInputStream(''))->isInteractive())->toBeFalse();
});
