<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Minimal symmetric encryption for secrets stored in the database.
 *
 * Uses libsodium secretbox with a key kept outside the database, in
 * config/local.config.php under ['security']['secret_key'] (base64 of 32 random
 * bytes). The goal is that a database dump alone (or an SQL injection) cannot
 * reveal the stored secrets; it is not a defense against an attacker who also
 * has the code and the local config, which is unavoidable since the application
 * must decrypt the secret to use it.
 *
 * A dedicated encryption subkey is derived from the configured master key, so
 * the same master key can safely serve other purposes (signing, etc.) without
 * cryptographic key reuse.
 *
 * When no valid key is configured, encryption is disabled and values are kept
 * in clear, so the feature is opt-in and backward compatible. Encrypted values
 * are tagged with a prefix to tell them apart from legacy clear values.
 */
final class Cipher
{
    const PREFIX = 'sodium:';

    /**
     * @var string|null Encryption subkey (32 bytes), or null when disabled.
     */
    private $key;

    public function __construct(?string $base64MasterKey)
    {
        $master = $base64MasterKey ? base64_decode($base64MasterKey, true) : false;
        $this->key = ($master !== false && strlen($master) >= SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
            ? hash_hkdf('sha256', $master, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'omeka:cipher:v1')
            : null;
    }

    public function isEnabled(): bool
    {
        return $this->key !== null;
    }

    /**
     * Encrypt a value, or return it unchanged when empty, already encrypted, or
     * when no key is configured.
     */
    public function encrypt(string $value): string
    {
        if ($value === '' || $this->key === null || strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0) {
            return $value;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, $this->key));
    }

    /**
     * Decrypt a value, or return it unchanged when it is a legacy clear value;
     * return an empty string when it is encrypted but cannot be decrypted.
     */
    public function decrypt(string $value): string
    {
        if (strncmp($value, self::PREFIX, strlen(self::PREFIX)) !== 0) {
            return $value;
        }
        if ($this->key === null) {
            return '';
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $min = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($raw === false || strlen($raw) < $min) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        return $plain === false ? '' : $plain;
    }
}
