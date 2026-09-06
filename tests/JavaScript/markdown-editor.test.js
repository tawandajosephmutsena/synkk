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
