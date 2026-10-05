<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

/**
 * Marks a #[Column] property as encrypted at rest.
 *
 * The value is converted for the database as usual, then encrypted via
 * Marko\Encryption\Contracts\EncryptorInterface (requires marko/encryption and a driver).
 * Encrypted columns are always stored as text.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Encrypted {}
