<?php

declare(strict_types=1);

namespace Fando\Keeper\Db;

/**
 * Stores the single CBS login (username/password) encrypted at rest with
 * libsodium secretbox. Only the admin screen touches this.
 */
final class CredentialsRepository
{
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $encryptionKeyHex,
    ) {
    }

    public function save(string $username, string $password): void
    {
        $key = $this->key();
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox($password, $nonce, $key);

        $stmt = $this->pdo->prepare(
            'INSERT INTO credentials (id, cbs_username, cbs_password_encrypted, updated_at)
             VALUES (1, :username, :encrypted, NOW())
             ON DUPLICATE KEY UPDATE cbs_username = :username2, cbs_password_encrypted = :encrypted2, updated_at = NOW()'
        );
        $stmt->execute([
            'username' => $username,
            'encrypted' => $nonce . $ciphertext,
            'username2' => $username,
            'encrypted2' => $nonce . $ciphertext,
        ]);
    }

    /** @return array{username: string, password: string}|null */
    public function load(): ?array
    {
        $stmt = $this->pdo->query('SELECT cbs_username, cbs_password_encrypted FROM credentials WHERE id = 1');
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $blob = $row['cbs_password_encrypted'];
        $nonce = substr($blob, 0, self::NONCE_BYTES);
        $ciphertext = substr($blob, self::NONCE_BYTES);
        $password = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());

        if ($password === false) {
            throw new \RuntimeException('Could not decrypt stored CBS credentials -- wrong encryption key?');
        }

        return ['username' => $row['cbs_username'], 'password' => $password];
    }

    private function key(): string
    {
        $key = sodium_hex2bin($this->encryptionKeyHex);
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('credentials_encryption_key must be a 32-byte hex string.');
        }
        return $key;
    }
}
