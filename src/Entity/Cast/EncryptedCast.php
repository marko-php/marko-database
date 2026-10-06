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
 *
 * The ciphertext is bound to its "table.column" as associated data, so a value copied
 * into another encrypted column (or another table) fails to decrypt.
 */
readonly class EncryptedCast implements CastInterface
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    /**
     * @throws DecryptionException|EncryptionException
     */
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): string {
        return $this->encryptor->decrypt((string) $value, $this->associatedData($meta));
    }

    /**
     * @throws EncryptionException
     */
    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): string {
        return $this->encryptor->encrypt(
            is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            $this->associatedData($meta),
        );
    }

    /**
     * The associated data binding a ciphertext to its column: "table.column".
     *
     * @throws EncryptionException
     */
    public function associatedData(
        PropertyMetadata $meta,
    ): string {
        if ($meta->tableName === '') {
            throw new EncryptionException(
                message: "Cannot encrypt property '$meta->name' without a table name",
                context: 'Encrypted columns are bound to "table.column" as associated data',
                suggestion: 'Build PropertyMetadata through EntityMetadataFactory, or pass tableName when constructing it',
            );
        }

        return "$meta->tableName.$meta->columnName";
    }
}
