<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use Marko\Database\Entity\PropertyMetadata;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;

/**
 * Cast applied to #[Encrypted] properties, on top of the property's normal conversion.
 *
 * On write it receives the already-converted database value (for example a JSON string
 * or an enum backing value) and encrypts its string form. On read it decrypts before the
 * normal conversion runs. Requires marko/encryption and a bound EncryptorInterface
 * (for example marko/encryption-openssl).
 */
class EncryptedCast implements CastInterface
{
    public function __construct(
        private readonly EncryptorInterface $encryptor,
    ) {}

    /**
     * @throws DecryptionException
     */
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): string {
        return $this->encryptor->decrypt((string) $value);
    }

    /**
     * @throws EncryptionException
     */
    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): string {
        return $this->encryptor->encrypt(is_bool($value) ? ($value ? '1' : '0') : (string) $value);
    }
}
