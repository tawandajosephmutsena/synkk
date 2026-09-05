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
