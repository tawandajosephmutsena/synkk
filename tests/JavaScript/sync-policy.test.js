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

test('syncs full plugin suite and themes while filtering desktop-only plugins on mobile', () => {
    const desktopSuite = {
        ...defaultSettings,
        syncPluginSuite: true,
        isMobile: false,
    };

    const mobileSuite = {
        ...defaultSettings,
        syncPluginSuite: true,
        isMobile: true,
    };

    // On desktop: syncs community-plugins, snippets, themes, and all plugins including desktop-only
    assert.equal(shouldSyncPath('.obsidian/community-plugins.json', desktopSuite), true);
    assert.equal(shouldSyncPath('.obsidian/snippets/callouts.css', desktopSuite), true);
    assert.equal(shouldSyncPath('.obsidian/themes/Minimal/theme.css', desktopSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/dataview/main.js', desktopSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/obsidian-git/main.js', desktopSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/shell-commands/main.js', desktopSuite), true);

    // On mobile: syncs universal plugins, snippets, and themes, but safely blocks desktop-only plugins
    assert.equal(shouldSyncPath('.obsidian/community-plugins.json', mobileSuite), true);
    assert.equal(shouldSyncPath('.obsidian/snippets/callouts.css', mobileSuite), true);
    assert.equal(shouldSyncPath('.obsidian/themes/Minimal/theme.css', mobileSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/dataview/main.js', mobileSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/kanban/main.js', mobileSuite), true);
    assert.equal(shouldSyncPath('.obsidian/plugins/obsidian-git/main.js', mobileSuite), false);
    assert.equal(shouldSyncPath('.obsidian/plugins/shell-commands/main.js', mobileSuite), false);
    assert.equal(shouldSyncPath('.obsidian/plugins/terminal/main.js', mobileSuite), false);

    // Still excludes ephemeral workspace and local state
    assert.equal(shouldSyncPath('.obsidian/workspace.json', mobileSuite), false);
    assert.equal(shouldSyncPath('.obsidian/workspace-mobile.json', mobileSuite), false);
    assert.equal(shouldSyncPath('.obsidian/hotkeys.json', mobileSuite), false);
    assert.equal(shouldSyncPath('.obsidian/synkk-state.json', mobileSuite), false);
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
