<?php

declare(strict_types=1);

namespace Fando\Keeper\Db;

/**
 * Stores the CBS session cookie (pasted by a human after logging into CBS
 * normally in their own browser -- see CbsClient) encrypted at rest with
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

    public function save(string $sessionCookie): void
    {
        $key = $this->key();
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox($sessionCookie, $nonce, $key);

        $stmt = $this->pdo->prepare(
            'INSERT INTO credentials (id, cbs_session_cookie_encrypted, updated_at)
             VALUES (1, :encrypted, NOW())
             ON DUPLICATE KEY UPDATE cbs_session_cookie_encrypted = :encrypted2, updated_at = NOW()'
        );
        $stmt->execute([
            'encrypted' => $nonce . $ciphertext,
            'encrypted2' => $nonce . $ciphertext,
        ]);
    }

    /** @return array{cookie: string, updated_at: string}|null */
    public function load(): ?array
    {
        $stmt = $this->pdo->query('SELECT cbs_session_cookie_encrypted, updated_at FROM credentials WHERE id = 1');
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $blob = $row['cbs_session_cookie_encrypted'];
        $nonce = substr($blob, 0, self::NONCE_BYTES);
        $ciphertext = substr($blob, self::NONCE_BYTES);
        $cookie = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());

        if ($cookie === false) {
            throw new \RuntimeException('Could not decrypt stored CBS session cookie -- wrong encryption key?');
        }

        return ['cookie' => $cookie, 'updated_at' => $row['updated_at']];
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
