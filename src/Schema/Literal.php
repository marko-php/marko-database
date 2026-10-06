<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

/**
 * A string column default that is always stored as a string, even when it reads like SQL.
 *
 * A plain string such as `'now()'` is a shortcut for an expression (see Expression::isShortcut()); wrap it in
 * a Literal (`#[Column(default: new Literal('now()'))]`) to store the text itself. Introspectors return a
 * Literal for a string default that would otherwise read as a shortcut.
 */
readonly class Literal
{
    public function __construct(
        public string $value,
    ) {}
}
