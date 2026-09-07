/**
 * Synkk In-Browser WebCrypto Passphrase Derivation and Zero-Knowledge E2EE Engine
 *
 * Implements client-side AES-256-GCM encryption with PBKDF2 key derivation (100,000 rounds).
 * Fully interoperable with the Synkk Obsidian Plugin (desktop & mobile) and backend sync.
 * Plaintext notes and passphrases NEVER leave the client browser unencrypted.
 */

export function uint8ToHex(bytes) {
    return Array.from(bytes)
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

export function hexToUint8(hex) {
    const clean = String(hex || '').replace(/[^0-9a-fA-F]/g, '');
    const bytes = new Uint8Array(clean.length / 2);
    for (let i = 0; i < clean.length; i += 2) {
        bytes[i / 2] = parseInt(clean.substring(i, i + 2), 16);
    }
    return bytes;
}

export function generateSalt() {
    const saltBytes = new Uint8Array(16);
    window.crypto.getRandomValues(saltBytes);
    return uint8ToHex(saltBytes);
}

export async function deriveKey(passphrase, saltHex) {
    if (!passphrase || !saltHex) {
        throw new Error('Passphrase and salt are required to derive encryption key.');
    }

    const enc = new TextEncoder();
    const baseKey = await window.crypto.subtle.importKey(
        'raw',
        enc.encode(passphrase),
        { name: 'PBKDF2' },
        false,
        ['deriveKey']
    );

    const saltBytes = hexToUint8(saltHex);

    return await window.crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: saltBytes,
            iterations: 100000,
            hash: 'SHA-256',
        },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

export async function encryptText(text, key) {
    if (!key) {
        throw new Error('CryptoKey is required to encrypt content.');
    }

    const iv = new Uint8Array(12);
    window.crypto.getRandomValues(iv);

    const enc = new TextEncoder();
    const raw = enc.encode(text);

    const encryptedBuf = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv,
        },
        key,
        raw
    );

    const fullEncrypted = new Uint8Array(encryptedBuf);
    const tagLength = 16;
    const cipherLength = fullEncrypted.length - tagLength;
    const tag = fullEncrypted.slice(cipherLength);

    // Convert fullEncrypted (ciphertext + tag) to binary base64 string
    let binary = '';
    const len = fullEncrypted.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(fullEncrypted[i]);
    }
    const ciphertextBase64 = btoa(binary);

    return {
        ciphertextBase64,
        ivHex: uint8ToHex(iv),
        tagHex: uint8ToHex(tag),
    };
}

export async function decryptText(ciphertextBase64, ivHex, tagHex, key) {
    if (!key) {
        throw new Error('CryptoKey is required to decrypt content.');
    }

    if (!ciphertextBase64 || !ivHex) {
        throw new Error('Missing ciphertext or IV for decryption.');
    }

    const binaryStr = atob(ciphertextBase64);
    const cipherBytes = new Uint8Array(binaryStr.length);
    for (let i = 0; i < binaryStr.length; i++) {
        cipherBytes[i] = binaryStr.charCodeAt(i);
    }

    const iv = hexToUint8(ivHex);
    let combined;

    if (tagHex && tagHex.length === 32) {
        const tagBytes = hexToUint8(tagHex);
        if (cipherBytes.length >= 16) {
            const last16Hex = uint8ToHex(cipherBytes.slice(cipherBytes.length - 16));
            if (last16Hex.toLowerCase() === tagHex.toLowerCase()) {
                combined = cipherBytes;
            } else {
                combined = new Uint8Array(cipherBytes.length + tagBytes.length);
                combined.set(cipherBytes, 0);
                combined.set(tagBytes, cipherBytes.length);
            }
        } else {
            combined = new Uint8Array(cipherBytes.length + tagBytes.length);
            combined.set(cipherBytes, 0);
            combined.set(tagBytes, cipherBytes.length);
        }
    } else {
        combined = cipherBytes;
    }

    const decrypted = await window.crypto.subtle.decrypt(
        {
            name: 'AES-GCM',
            iv,
        },
        key,
        combined
    );

    const dec = new TextDecoder();
    return dec.decode(decrypted);
}

export async function createVerificationCipher(key) {
    const testPlaintext = 'synkk-e2ee-verify-token-v1';
    const res = await encryptText(testPlaintext, key);
    return JSON.stringify(res);
}

export async function verifyPassphrase(passphrase, saltHex, testCipherJson) {
    try {
        const key = await deriveKey(passphrase, saltHex);
        if (!testCipherJson) {
            return { success: true, key };
        }

        const parsed = typeof testCipherJson === 'string' ? JSON.parse(testCipherJson) : testCipherJson;
        const decrypted = await decryptText(parsed.ciphertextBase64, parsed.ivHex, parsed.tagHex, key);

        if (decrypted === 'synkk-e2ee-verify-token-v1') {
            return { success: true, key };
        }

        return { success: false, error: 'Incorrect vault passphrase.' };
    } catch (err) {
        return { success: false, error: err.message || 'Passphrase verification failed.' };
    }
}

// Session Vault Key Store (in-memory per tab session)
const vaultKeys = new Map();

export const VaultCrypto = {
    uint8ToHex,
    hexToUint8,
    generateSalt,
    deriveKey,
    encryptText,
    decryptText,
    createVerificationCipher,
    verifyPassphrase,

    setSessionKey(vaultSlug, key) {
        vaultKeys.set(vaultSlug, key);
        try {
            sessionStorage.setItem(`synkk_unlocked_${vaultSlug}`, 'true');
        } catch {}
    },

    getSessionKey(vaultSlug) {
        return vaultKeys.get(vaultSlug) || null;
    },

    hasSessionKey(vaultSlug) {
        return vaultKeys.has(vaultSlug);
    },

    clearSessionKey(vaultSlug) {
        vaultKeys.delete(vaultSlug);
        try {
            sessionStorage.removeItem(`synkk_unlocked_${vaultSlug}`);
        } catch {}
    },
};

if (typeof window !== 'undefined') {
    window.VaultCrypto = VaultCrypto;
}

export default VaultCrypto;
