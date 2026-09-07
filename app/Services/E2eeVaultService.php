<?php

namespace App\Services;

use App\Models\Vault;
use RuntimeException;

class E2eeVaultService
{
    /**
     * Enable client-side zero-knowledge End-to-End Encryption on a vault.
     */
    public function enable(Vault $vault, string $saltHex, string $testCipher): Vault
    {
        if (strlen($saltHex) < 16) {
            throw new RuntimeException('Invalid cryptographic salt. Expected hex-encoded salt of at least 16 characters.');
        }

        if (empty($testCipher)) {
            throw new RuntimeException('Missing verification test cipher for client passphrase validation.');
        }

        $vault->update([
            'is_e2ee' => true,
            'e2ee_salt' => $saltHex,
            'e2ee_test_cipher' => $testCipher,
        ]);

        return $vault;
    }

    /**
     * Alias for enable.
     */
    public function enableE2ee(Vault $vault, string $saltHex, string $testCipher): Vault
    {
        return $this->enable($vault, $saltHex, $testCipher);
    }

    /**
     * Disable E2EE on a vault.
     */
    public function disable(Vault $vault): Vault
    {
        $vault->update([
            'is_e2ee' => false,
            'e2ee_salt' => null,
            'e2ee_test_cipher' => null,
        ]);

        return $vault;
    }

    /**
     * Alias for disable.
     */
    public function disableE2ee(Vault $vault): Vault
    {
        return $this->disable($vault);
    }

    /**
     * Get the E2EE status and configuration for client key derivation.
     *
     * @return array{
     *     is_e2ee: bool,
     *     salt: string|null,
     *     test_cipher: string|null,
     *     cipher_algorithm: string,
     *     kdf: string,
     *     iterations: int
     * }
     */
    public function getStatus(Vault $vault): array
    {
        return [
            'is_e2ee' => (bool) $vault->is_e2ee,
            'salt' => $vault->e2ee_salt,
            'test_cipher' => $vault->e2ee_test_cipher,
            'has_test_cipher' => ! empty($vault->e2ee_test_cipher),
            'cipher_algorithm' => 'AES-GCM-256',
            'kdf' => 'PBKDF2-HMAC-SHA256',
            'iterations' => 100000,
        ];
    }
}
