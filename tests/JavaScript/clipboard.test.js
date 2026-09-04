import assert from 'node:assert/strict';
import test from 'node:test';

import { copyTextToClipboard } from '../../resources/js/clipboard.js';

test('copies text with the asynchronous Clipboard API', async () => {
    let copiedText = null;

    const result = await copyTextToClipboard('synkk_primary', {
        clipboard: {
            async writeText(text) {
                copiedText = text;
            },
        },
        fallbackCopy: () => false,
    });

    assert.deepEqual(result, { copied: true, method: 'clipboard' });
    assert.equal(copiedText, 'synkk_primary');
});

test('falls back when the Clipboard API rejects the write', async () => {
    let fallbackText = null;

    const result = await copyTextToClipboard('synkk_fallback', {
        clipboard: {
            async writeText() {
                throw new Error('Clipboard permission denied');
            },
        },
        fallbackCopy(text) {
            fallbackText = text;

            return true;
        },
    });

    assert.deepEqual(result, { copied: true, method: 'fallback' });
    assert.equal(fallbackText, 'synkk_fallback');
});

test('reports failure when neither copy method succeeds', async () => {
    const result = await copyTextToClipboard('synkk_manual', {
        clipboard: null,
        fallbackCopy: () => false,
    });

    assert.deepEqual(result, { copied: false, method: null });
});
