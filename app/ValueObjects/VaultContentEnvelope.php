<?php

namespace App\ValueObjects;

use App\Models\Vault;
use Illuminate\Validation\ValidationException;

final readonly class VaultContentEnvelope
{
    public function __construct(
        public string $payload,
        public string $payloadSha256,
        public int $plaintextSize,
        public bool $encrypted,
        public ?string $iv = null,
        public ?string $tag = null,
        public bool $ghost = false,
        public ?string $mimeType = null,
        public int $formatVersion = 2,
    ) {}

    /**
     * Create and validate an envelope from validated request data and vault policy.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function fromValidated(array $data, Vault $vault, ?string $rawPayload = null): self
    {
        $payload = $rawPayload;

        if ($payload === null) {
            if (isset($data['content_base64'])) {
                $rawBase64 = (string) $data['content_base64'];
                if ($rawBase64 === '') {
                    $payload = '';
                } else {
                    $decoded = base64_decode($rawBase64, true);
                    if ($decoded === false) {
                        throw ValidationException::withMessages([
                            'content_base64' => ['The provided Base64 content is malformed.'],
                        ]);
                    }
                    $payload = $decoded;
                }
            } elseif (array_key_exists('content', $data)) {
                $payload = (string) $data['content'];
            } else {
                throw ValidationException::withMessages([
                    'content' => ['No content or payload provided.'],
                ]);
            }
        }

        $payloadSha256 = hash('sha256', $payload);
        $isEncrypted = (bool) ($data['encrypted'] ?? $data['is_encrypted'] ?? false);
        $iv = isset($data['iv']) ? (string) $data['iv'] : (isset($data['encryption_iv']) ? (string) $data['encryption_iv'] : null);
        $tag = isset($data['tag']) ? (string) $data['tag'] : (isset($data['encryption_tag']) ? (string) $data['encryption_tag'] : null);
        $formatVersion = (int) ($data['format_version'] ?? 2);
        $isGhost = (bool) ($data['ghost'] ?? $data['is_ghost'] ?? false);
        $plaintextSize = (int) ($data['plaintext_size'] ?? $data['original_size'] ?? strlen($payload));
        $mimeType = isset($data['mime_type']) ? (string) $data['mime_type'] : null;

        if ($vault->is_e2ee) {
            $errors = [];

            if (! $isEncrypted) {
                $errors['encrypted'] = ['Encrypted vaults require a client-encrypted payload.'];
            }

            $ivLength = $iv !== null ? strlen($iv) : 0;
            $isHexIv = $iv !== null && ctype_xdigit($iv);
            if (! $isHexIv || $ivLength !== 24) {
                $errors['iv'] = ['AES-GCM encryption IV must be a 24-character hexadecimal string (12 bytes).'];
            }

            $tagLength = $tag !== null ? strlen($tag) : 0;
            $isHexTag = $tag !== null && ctype_xdigit($tag);
            if (! $isHexTag || $tagLength !== 32) {
                $errors['tag'] = ['AES-GCM authentication tag must be a 32-character hexadecimal string (16 bytes).'];
            }

            if ($formatVersion < 2) {
                $errors['format_version'] = ['Encrypted vault payloads require format version 2 or higher.'];
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }

        return new self(
            payload: $payload,
            payloadSha256: $payloadSha256,
            plaintextSize: $plaintextSize,
            encrypted: $isEncrypted,
            iv: $iv,
            tag: $tag,
            ghost: $isGhost,
            mimeType: $mimeType,
            formatVersion: $formatVersion,
        );
    }
}
