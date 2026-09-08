import assert from 'node:assert/strict';
import test from 'node:test';

import { createMarkdownEditor, renderMarkdownPreview } from '../../resources/js/markdown-editor.js';

test('renders useful Markdown locally while escaping unsafe content', () => {
    const html = renderMarkdownPreview(`# Launch plan

Use **clear ownership**, link [[Release Notes]], and reject [unsafe links](javascript:alert(1)).

<script>alert('no')</script>`);

    assert.match(html, /<h1>Launch plan<\/h1>/);
    assert.match(html, /<strong>clear ownership<\/strong>/);
    assert.match(html, /class="synkk-wiki-link">Release Notes<\/span>/);
    assert.match(html, /href="#"/);
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
});

test('computes editor metrics, outline, and preview from the local buffer', () => {
    const editor = createMarkdownEditor({
        content: '# Brief\n\n## Decisions\n\n- Ship safely',
        canEdit: true,
    });

    editor.updateMetrics();

    assert.equal(editor.lineCount, 5);
    assert.equal(editor.wordCount, 4);
    assert.equal(editor.headings.length, 2);
    assert.equal(editor.headings[1].title, 'Decisions');
    assert.match(editor.previewHtml, /<ul><li>Ship safely<\/li><\/ul>/);
});

test('saves one local buffer and clears dirty state only after success', async () => {
    const editor = createMarkdownEditor({ content: 'Original', canEdit: true });
    let saveCalls = 0;

    editor.content = 'Updated';
    editor.isDirty = true;
    editor.$wire = {
        editorContent: '',
        editorIsDirty: true,
        async saveFile() {
            saveCalls += 1;
        },
    };

    const saved = await editor.saveEditor();

    assert.equal(saved, true);
    assert.equal(saveCalls, 1);
    assert.equal(editor.$wire.editorContent, 'Updated');
    assert.equal(editor.isDirty, false);
    assert.equal(editor.initialContent, 'Updated');
});

test('renders expanded Obsidian callout themes, math formulas, and highlights', () => {
    const markdown = `> [!BUG] Memory leak in sync
> Identified circular buffer references.

> [!SUCCESS] Verification passed
> All tests green.

> [!DANGER] Critical threshold
> Do not delete files.

Here is an inline math formula $E = mc^2$ and ==highlighted text==.

$$
\\sum_{i=1}^{n} x_i = X
$$`;

    const html = renderMarkdownPreview(markdown);

    assert.match(html, /class="synkk-callout synkk-callout--bug"/);
    assert.match(html, /Memory leak in sync/);
    assert.match(html, /class="synkk-callout synkk-callout--success"/);
    assert.match(html, /Verification passed/);
    assert.match(html, /class="synkk-callout synkk-callout--danger"/);
    assert.match(html, /class="synkk-math-inline"/);
    assert.match(html, /E = mc\^2/);
    assert.match(html, /class="synkk-highlight">highlighted text<\/mark>/);
    assert.match(html, /class="synkk-math-block"/);
    assert.match(html, /\\sum_\{i=1\}\^\{n\} x_i = X/);
});

test('renders Mermaid diagrams, Dataview queries, and Kanban boards', () => {
    const markdown = `\`\`\`mermaid
graph TD
    A[Client] --> B[Synkk Server]
\`\`\`

\`\`\`dataview
TABLE file.mtime AS "Modified" FROM "Projects"
\`\`\`

\`\`\`kanban
## Backlog
- [ ] Task 1
- [ ] Task 2
## In Progress
- [ ] Task 3
\`\`\``;

    const html = renderMarkdownPreview(markdown);

    assert.match(html, /class="synkk-mermaid-block"/);
    assert.match(html, /MERMAID DIAGRAM/);
    assert.match(html, /graph TD/);

    assert.match(html, /class="synkk-dataview-block"/);
    assert.match(html, /DATAVIEW/);
    assert.match(html, /class="synkk-dataview-type">TABLE<\/span>/);

    assert.match(html, /class="synkk-kanban-board"/);
    assert.match(html, /class="synkk-kanban-col-header"><strong>Backlog<\/strong>/);
    assert.match(html, /Task 1/);
    assert.match(html, /Task 3/);
});

test('initializes Yjs collaboration and seeds ytext from existing content snapshot', async () => {
    const editor = createMarkdownEditor({
        content: '# Collaborative Note\nInitial text.',
        documentId: 101,
        filePath: 'Notes/Collab.md',
        canEdit: true,
        collabEnabled: true,
    });

    await editor.initCollaboration();

    assert.ok(editor.ydoc);
    assert.ok(editor.ytext);
    assert.ok(editor.awareness);
    assert.equal(editor.ytext.toString(), '# Collaborative Note\nInitial text.');
    assert.equal(editor.awareness.getLocalState()?.user?.name, 'Web Editor');

    editor.destroy();
});

test('remote Yjs updates update editor content and metrics without marking initially dirty', async () => {
    const editor = createMarkdownEditor({
        content: 'Original',
        documentId: 102,
        filePath: 'Notes/Remote.md',
        canEdit: true,
        collabEnabled: true,
    });

    await editor.initCollaboration();

    // Simulate remote peer editing the document
    editor.ydoc.transact(() => {
        editor.ytext.delete(0, editor.ytext.length);
        editor.ytext.insert(0, '# Remote Title\nUpdated collaboratively.');
    }, 'remote');

    assert.equal(editor.content, '# Remote Title\nUpdated collaboratively.');
    assert.equal(editor.headings.length, 1);
    assert.equal(editor.headings[0].title, 'Remote Title');

    editor.destroy();
});

test('local content update synchronizes to ytext in transaction', async () => {
    const editor = createMarkdownEditor({
        content: 'Start',
        documentId: 103,
        filePath: 'Notes/Local.md',
        canEdit: true,
        collabEnabled: true,
    });

    // Mock Alpine $watch
    let watchHandler;
    editor.$watch = (prop, handler) => {
        if (prop === 'content') watchHandler = handler;
    };

    await editor.init();

    editor.content = 'Start\nNew local line.';
    if (watchHandler) watchHandler(editor.content);

    assert.equal(editor.ytext.toString(), 'Start\nNew local line.');
    assert.equal(editor.isDirty, true);

    editor.destroy();
});

test('note switching and component teardown cleanly destroy collaboration resources', async () => {
    const editor = createMarkdownEditor({
        content: 'Note 1',
        documentId: 104,
        filePath: 'Notes/Note1.md',
        canEdit: true,
        collabEnabled: true,
    });

    await editor.initCollaboration();
    assert.ok(editor.ydoc);
    assert.ok(editor.provider);

    let selectFileCalledWith = null;
    editor.$wire = {
        selectFile: async (id) => { selectFileCalledWith = id; },
    };

    await editor.openFile(205);

    assert.equal(selectFileCalledWith, 205);
    assert.equal(editor.ydoc, null);
    assert.equal(editor.ytext, null);
    assert.equal(editor.provider, null);
    assert.equal(editor.awareness, null);

    editor.destroy();
});

test('passphrase locking clears session keys, resets plaintext, and cleans up collaboration', async () => {
    let clearedSlug = null;
    globalThis.window = {
        VaultCrypto: {
            hasSessionKey: () => true,
            clearSessionKey: (slug) => { clearedSlug = slug; },
        },
    };

    try {
        const editor = createMarkdownEditor({
            content: 'Secret decrypted text',
            documentId: 105,
            filePath: 'Notes/Secret.md',
            vaultSlug: 'my-e2ee-vault',
            isEncrypted: true,
            canEdit: true,
            collabEnabled: true,
        });

        editor.initialContent = 'U2FsdGVkX18...'; // encrypted ciphertext snapshot
        editor.isUnlocked = true;
        await editor.initCollaboration();

        editor.lockPassphrase();

        assert.equal(clearedSlug, 'my-e2ee-vault');
        assert.equal(editor.isUnlocked, false);
        assert.equal(editor.ydoc, null);
        assert.equal(editor.provider, null);
        assert.equal(editor.content, 'U2FsdGVkX18...');
    } finally {
        delete globalThis.window;
    }
});

test('read-only mode refuses to save or schedule snapshot flushes', async () => {
    const editor = createMarkdownEditor({
        content: 'Read only text',
        documentId: 106,
        filePath: 'Notes/ReadOnly.md',
        canEdit: false,
        collabEnabled: true,
    });

    await editor.initCollaboration();
    editor.isDirty = true;
    editor.$wire = {
        editorContent: '',
        saveFile: async () => { throw new Error('Save should not be called'); },
    };

    const saved = await editor.saveEditor();
    assert.equal(saved, false);

    editor.scheduleSnapshotFlush();
    assert.equal(editor.snapshotTimer, null);

    editor.destroy();
});

test('CRITICAL E2EE SECURITY: Never assigns plaintext to Livewire properties for encrypted notes', async () => {
    const editor = createMarkdownEditor({
        content: 'Secret Decrypted Plaintext',
        documentId: 107,
        filePath: 'Notes/Confidential.md',
        vaultSlug: 'secure-vault',
        isEncrypted: true,
        canEdit: true,
        collabEnabled: false,
    });

    let wireContent = 'INITIAL_CIPHERTEXT_BASE64';
    let wireDirty = false;
    editor.$wire = {
        get editorContent() { return wireContent; },
        set editorContent(val) { wireContent = val; },
        set editorIsDirty(val) { wireDirty = val; },
    };

    let watchHandler;
    editor.$watch = (prop, handler) => {
        if (prop === 'content') watchHandler = handler;
    };

    await editor.init();

    // User types new decrypted plaintext
    editor.content = 'New classified thoughts entered here';
    if (watchHandler) watchHandler(editor.content);

    // Livewire server state MUST NOT be updated with plaintext!
    assert.equal(wireContent, 'INITIAL_CIPHERTEXT_BASE64');
    assert.equal(wireDirty, true);
    assert.equal(editor.isDirty, true);

    editor.destroy();
});

test('two independent editor sessions converge deterministically on concurrent edits', async () => {
    const Y = await import('yjs');

    const session1 = createMarkdownEditor({
        content: 'Heading\nParagraph',
        documentId: 108,
        filePath: 'Notes/Shared.md',
        canEdit: true,
        collabEnabled: true,
    });
    await session1.initCollaboration();

    // Session 2 starts from session 1's initial state
    const session2 = createMarkdownEditor({
        content: '',
        documentId: 108,
        filePath: 'Notes/Shared.md',
        canEdit: true,
        collabEnabled: true,
    });
    await session2.initCollaboration();

    const initialState = Y.encodeStateAsUpdate(session1.ydoc);
    Y.applyUpdate(session2.ydoc, initialState);
    session2.content = session2.ytext.toString();

    // Peer 1 inserts text at start of line 2
    session1.ydoc.transact(() => {
        session1.ytext.insert(8, 'Important ');
    }, 'local');

    // Peer 2 inserts text at end of line 2
    session2.ydoc.transact(() => {
        session2.ytext.insert(17, ' with details.');
    }, 'local');

    // Exchange concurrent updates
    const u1 = Y.encodeStateAsUpdate(session1.ydoc);
    const u2 = Y.encodeStateAsUpdate(session2.ydoc);

    Y.applyUpdate(session2.ydoc, u1);
    Y.applyUpdate(session1.ydoc, u2);

    assert.equal(session1.ytext.toString(), session2.ytext.toString());
    assert.equal(session1.content, session2.content);
    assert.match(session1.content, /Important Paragraph with details\./);

    session1.destroy();
    session2.destroy();
});

test('editor transport dispatches local updates via sendUpdate or appendUpdate', async () => {
    let sentUpdate = null;
    let checkpointCalled = false;

    // Mock fetch for transport
    globalThis.fetch = async (url, options) => {
        if (url.includes('/catch-up')) {
            return {
                json: async () => ({ updates: [] }),
            };
        }
        if (url.includes('/append')) {
            const body = JSON.parse(options.body);
            sentUpdate = body;
            return {
                json: async () => ({
                    status: 'committed',
                    document_id: body.document_id,
                    update: { sequence: 1, client_update_id: body.client_update_id },
                }),
            };
        }
        if (url.includes('/checkpoint')) {
            checkpointCalled = true;
            return {
                json: async () => ({ status: 'checkpoint_committed' }),
            };
        }
        return { json: async () => ({}) };
    };

    const editor = createMarkdownEditor({
        content: 'Start Text',
        documentId: 201,
        filePath: 'Test/Doc.md',
        canEdit: true,
        collabEnabled: true,
        vaultSlug: 'test-vault',
    });

    let watchHandler;
    editor.$watch = (prop, handler) => {
        if (prop === 'content') watchHandler = handler;
    };

    await editor.init();

    // Trigger local edit
    editor.content = 'Start Text with modification';
    if (watchHandler) watchHandler(editor.content);

    await editor.provider.waitForPending();

    assert.ok(sentUpdate, 'Expected transport to be invoked on local edit');
    assert.equal(sentUpdate.document_id, 201);
    assert.equal(sentUpdate.path, 'Test/Doc.md');
    assert.ok(sentUpdate.payload, 'Expected payload to be present');
    assert.equal(sentUpdate.encrypted, false);

    // Verify checkpoint works
    await editor.provider.checkpoint();
    assert.ok(checkpointCalled, 'Expected checkpoint to be called');

    editor.destroy();
});
