import assert from 'node:assert/strict';
import test from 'node:test';

import { deletionGuard, shouldSyncPath } from '../../obsidian-plugin/src/sync-policy.js';

const defaultSettings = {
    includedPaths: '',
    excludedPaths: '',
    syncPluginList: false,
    syncSnippets: false,
    syncPluginData: false,
};

test('syncs vault content and permanently excludes local Synkk and system state', () => {
    assert.equal(shouldSyncPath('Notes/Launch.md', defaultSettings), true);
    assert.equal(shouldSyncPath('.git/config', defaultSettings), false);
    assert.equal(shouldSyncPath('.trash/Deleted.md', defaultSettings), false);
    assert.equal(shouldSyncPath('.synkk/snapshots/2026-09-05/Launch.md', defaultSettings), false);
    assert.equal(shouldSyncPath('Notes/.DS_Store', defaultSettings), false);
});

test('applies inclusive and exclusive folder rules using whole path prefixes', () => {
    const settings = {
        ...defaultSettings,
        includedPaths: '00-Inbox\nProjects',
        excludedPaths: 'Projects/Archive',
    };

    assert.equal(shouldSyncPath('00-Inbox/Idea.md', settings), true);
    assert.equal(shouldSyncPath('Projects/Launch.md', settings), true);
    assert.equal(shouldSyncPath('Projects/Archive/Old.md', settings), false);
    assert.equal(shouldSyncPath('Personal/Journal.md', settings), false);
});

test('keeps Obsidian configuration device-local unless its specific category is enabled', () => {
    assert.equal(shouldSyncPath('.obsidian/community-plugins.json', defaultSettings), false);
    assert.equal(shouldSyncPath('.obsidian/snippets/brand.css', defaultSettings), false);
    assert.equal(shouldSyncPath('.obsidian/plugins/calendar/data.json', defaultSettings), false);

    const configuredSettings = {
        ...defaultSettings,
        syncPluginList: true,
        syncSnippets: true,
        syncPluginData: true,
    };

    assert.equal(shouldSyncPath('.obsidian/community-plugins.json', configuredSettings), true);
    assert.equal(shouldSyncPath('.obsidian/snippets/brand.css', configuredSettings), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/calendar/data.json', configuredSettings), true);
    assert.equal(shouldSyncPath('.obsidian/workspace.json', configuredSettings), false);
    assert.equal(shouldSyncPath('.obsidian/workspace-mobile.json', configuredSettings), false);
    assert.equal(shouldSyncPath('.obsidian/hotkeys.json', configuredSettings), false);
    assert.equal(shouldSyncPath('.obsidian/synkk-state.json', configuredSettings), false);
});

test('allows a deletion set at the threshold and blocks a larger set without an override', () => {
    assert.deepEqual(deletionGuard(['One.md'], 10, 10, false), {
        blocked: false,
        percentage: 10,
    });

    assert.deepEqual(deletionGuard(['One.md', 'Two.md'], 10, 10, false), {
        blocked: true,
        percentage: 20,
    });
});

test('honors a one-time deletion override and treats an empty baseline as safe', () => {
    assert.deepEqual(deletionGuard(['One.md', 'Two.md'], 10, 10, true), {
        blocked: false,
        percentage: 20,
    });

    assert.deepEqual(deletionGuard(['One.md'], 0, 10, false), {
        blocked: false,
        percentage: 0,
    });
});
