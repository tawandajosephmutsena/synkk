/**
 * Synkk Protocol v2 - Encrypted Yjs Update Codec
 *
 * Provides authenticated AES-256-GCM encryption and decryption for Yjs binary updates.
 * Additional Authenticated Data (AAD) cryptographically binds updates to their vault,
 * document ID, purpose ('yjs-update' or 'checkpoint'), and protocol format version.
 */

export function uint8ToHex(bytes) {
  return Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

export function hexToUint8(hex) {
  const clean = hex.replace(/[^0-9a-fA-F]/g, '');
  const bytes = new Uint8Array(clean.length / 2);
  for (let i = 0; i < clean.length; i += 2) {
    bytes[i / 2] = parseInt(clean.substring(i, i + 2), 16);
  }
  return bytes;
}

export function uint8ToBase64(bytes) {
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(bytes).toString('base64');
  }
  let binary = '';
  const len = bytes.byteLength;
  for (let i = 0; i < len; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
}

export function base64ToUint8(base64) {
  if (typeof Buffer !== 'undefined') {
    return new Uint8Array(Buffer.from(base64, 'base64'));
  }
  const binary = atob(base64);
  const len = binary.length;
  const bytes = new Uint8Array(len);
  for (let i = 0; i < len; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes;
}

export async function sha256Hex(bytes) {
  const hashBuf = await crypto.subtle.digest('SHA-256', bytes);
  return uint8ToHex(new Uint8Array(hashBuf));
}

/**
 * Format Additional Authenticated Data (AAD) for AES-GCM framing.
 */
export function encodeAad({ vaultId, documentId, purpose = 'yjs-update', formatVersion = 2 }) {
  const aadString = `synkk-v2:${vaultId}:${documentId}:${purpose}:${formatVersion}`;
  return new TextEncoder().encode(aadString);
}

/**
 * Encrypt a plaintext Yjs binary update with AAD binding.
 *
 * @param {Uint8Array} plaintextUpdate
 * @param {CryptoKey} cryptoKey
 * @param {{ vaultId: string|number, documentId: string|number, purpose?: string, formatVersion?: number }} options
 * @returns {Promise<{ ciphertextBase64: string, ivHex: string, tagHex: string, payloadSha256: string, formatVersion: number }>}
 */
export async function encodeEncryptedUpdate(plaintextUpdate, cryptoKey, { vaultId, documentId, purpose = 'yjs-update', formatVersion = 2 }) {
  if (!cryptoKey) {
    throw new Error('E2EE key is required to encrypt update.');
  }

  const iv = new Uint8Array(12);
  crypto.getRandomValues(iv);

  const aad = encodeAad({ vaultId, documentId, purpose, formatVersion });

  const rawBytes = plaintextUpdate instanceof Uint8Array ? plaintextUpdate : new Uint8Array(plaintextUpdate);
  const encryptedBuf = await crypto.subtle.encrypt(
    {
      name: 'AES-GCM',
      iv,
      additionalData: aad,
      tagLength: 128,
    },
    cryptoKey,
    rawBytes
  );

  const fullEncrypted = new Uint8Array(encryptedBuf);
  const tagLength = 16;
  const cipherLength = fullEncrypted.length - tagLength;
  const ciphertext = fullEncrypted.slice(0, cipherLength);
  const tag = fullEncrypted.slice(cipherLength);

  const ciphertextBase64 = uint8ToBase64(ciphertext);
  const payloadSha256 = await sha256Hex(new TextEncoder().encode(ciphertextBase64));

  return {
    ciphertextBase64,
    ivHex: uint8ToHex(iv),
    tagHex: uint8ToHex(tag),
    payloadSha256,
    formatVersion,
  };
}

/**
 * Decrypt an encrypted update envelope with AAD authentication.
 *
 * @param {{ ciphertextBase64: string, ivHex: string, tagHex: string, formatVersion?: number }} envelope
 * @param {CryptoKey} cryptoKey
 * @param {{ vaultId: string|number, documentId: string|number, purpose?: string }} options
 * @returns {Promise<Uint8Array>}
 */
export async function decodeEncryptedUpdate(
  { ciphertextBase64, ivHex, tagHex, formatVersion = 2 },
  cryptoKey,
  { vaultId, documentId, purpose = 'yjs-update' }
) {
  if (!cryptoKey) {
    throw new Error('E2EE key is required to decrypt update.');
  }

  const ciphertextBytes = base64ToUint8(ciphertextBase64);
  const iv = hexToUint8(ivHex);
  const tagBytes = hexToUint8(tagHex);

  const combined = new Uint8Array(ciphertextBytes.length + tagBytes.length);
  combined.set(ciphertextBytes, 0);
  combined.set(tagBytes, ciphertextBytes.length);

  const aad = encodeAad({ vaultId, documentId, purpose, formatVersion });

  try {
    const decryptedBuf = await crypto.subtle.decrypt(
      {
        name: 'AES-GCM',
        iv,
        additionalData: aad,
        tagLength: 128,
      },
      cryptoKey,
      combined
    );

    return new Uint8Array(decryptedBuf);
  } catch (error) {
    throw new Error('E2EE update authentication failed: ' + (error?.message || 'invalid key or tampered update.'));
  }
}
