import assert from 'node:assert/strict';
import test from 'node:test';

import { reverbConnectionOptions } from '../../resources/js/reverb-connection.js';

const documentWithKey = (key) => ({
    querySelector: () => ({ content: key }),
});

test('uses the browser HTTPS host and custom port for a packaged install', () => {
    const options = reverbConnectionOptions(
        { protocol: 'https:', hostname: 'umbrel.local', port: '14965' },
        documentWithKey('install-public-key'),
        {},
    );

    assert.deepEqual(options, {
        key: 'install-public-key',
        wsHost: 'umbrel.local',
        wsPort: 14965,
        wssPort: 14965,
        forceTLS: true,
    });
});

test('ignores local build settings in production', () => {
    const options = reverbConnectionOptions(
        { protocol: 'https:', hostname: 'synkk.example', port: '' },
        documentWithKey('install-public-key'),
        {
            DEV: false,
            VITE_REVERB_APP_KEY: 'local-build-key',
            VITE_REVERB_HOST: 'devbox.test',
            VITE_REVERB_PORT: '8080',
            VITE_REVERB_SCHEME: 'http',
        },
    );

    assert.deepEqual(options, {
        key: 'install-public-key',
        wsHost: 'synkk.example',
        wsPort: 443,
        wssPort: 443,
        forceTLS: true,
    });
});

test('does not use a local build key when the production page has no Reverb key', () => {
    const options = reverbConnectionOptions(
        { protocol: 'https:', hostname: 'synkk.example', port: '' },
        { querySelector: () => null },
        { DEV: false, VITE_REVERB_APP_KEY: 'local-build-key' },
    );

    assert.equal(options.key, undefined);
});

test('uses the standard HTTPS port when the browser has no explicit port', () => {
    const options = reverbConnectionOptions(
        { protocol: 'https:', hostname: 'synkk.example', port: '' },
        documentWithKey('install-public-key'),
        {},
    );

    assert.equal(options.wssPort, 443);
    assert.equal(options.forceTLS, true);
});

test('keeps explicit local development settings and falls back to the build key', () => {
    const options = reverbConnectionOptions(
        { protocol: 'http:', hostname: 'synkk.test', port: '' },
        { querySelector: () => null },
        {
            DEV: true,
            VITE_REVERB_APP_KEY: 'build-public-key',
            VITE_REVERB_HOST: 'localhost',
            VITE_REVERB_PORT: '8080',
            VITE_REVERB_SCHEME: 'http',
        },
    );

    assert.deepEqual(options, {
        key: 'build-public-key',
        wsHost: 'synkk.test',
        wsPort: 8080,
        wssPort: 8080,
        forceTLS: false,
    });
});
