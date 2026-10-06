import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const buildDirectory = new URL('../../public/build/', import.meta.url);
const manifest = JSON.parse(readFileSync(new URL('manifest.json', buildDirectory), 'utf8'));

test('production build contains every Synkk page entrypoint', () => {
    const entrypoints = [
        'resources/css/app.css',
        'resources/css/landing.css',
        'resources/css/portal.css',
        'resources/js/app.js',
        'resources/js/landing.js',
        'resources/js/passkeys.js',
        'resources/js/portal.js',
    ];

    for (const entrypoint of entrypoints) {
        assert.ok(manifest[entrypoint]?.file, `${entrypoint} is missing from the Vite manifest`);
        assert.ok(existsSync(new URL(manifest[entrypoint].file, buildDirectory)), `${entrypoint} has no built asset`);
    }
});
