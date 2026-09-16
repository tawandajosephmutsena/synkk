import assert from 'node:assert/strict';
import test from 'node:test';
import * as Y from 'yjs';
import {
    extractDocumentMetrics,
    extractDocumentHeadings,
} from '../../resources/js/editor/synkk-codemirror.js';
import { SynkkAwareness } from '../../resources/js/collaboration/yjs-provider.js';
import { EditorState, Text } from '@codemirror/state';
import { markdown, markdownLanguage } from '@codemirror/lang-markdown';

test('extractDocumentMetrics accurately computes words, characters, and lines', () => {
    const doc = Text.of([
        '# Welcome to Synkk',
        '',
        'This is an enterprise collaborative CodeMirror 6 editor.',
        'It tracks word count and lines accurately.',
    ]);

    const metrics = extractDocumentMetrics(doc);
    assert.equal(metrics.lineCount, 4);
    assert.equal(metrics.words, 19);
    assert.ok(metrics.characters > 50);
});

test('extractDocumentHeadings extracts outline hierarchy from document', () => {
    const doc = Text.of([
        '# Architecture Overview',
        'Some notes here.',
        '## CRDT & Yjs Engine',
        'Details on CRDT.',
        '### Reverb WebSockets',
        'Real-time transport.',
        '## Security & DLP',
    ]);

    const headings = extractDocumentHeadings(doc);
    assert.equal(headings.length, 4);

    assert.equal(headings[0].level, 1);
    assert.equal(headings[0].title, 'Architecture Overview');
    assert.equal(headings[0].line, 1);

    assert.equal(headings[1].level, 2);
    assert.equal(headings[1].title, 'CRDT & Yjs Engine');
    assert.equal(headings[1].line, 3);

    assert.equal(headings[2].level, 3);
    assert.equal(headings[2].title, 'Reverb WebSockets');
    assert.equal(headings[2].line, 5);

    assert.equal(headings[3].level, 2);
    assert.equal(headings[3].title, 'Security & DLP');
    assert.equal(headings[3].line, 7);
});

test('Obsidian Wikilink syntax parsing matches targets and aliases', () => {
    const text = 'Check out [[08 Projects/Synkk|Synkk Project]] and also [[Welcome]].';
    const regex = /\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g;

    const matches = [];
    let match;
    while ((match = regex.exec(text)) !== null) {
        matches.push({
            target: match[1].trim(),
            alias: match[2]?.trim() || null,
        });
    }

    assert.equal(matches.length, 2);
    assert.equal(matches[0].target, '08 Projects/Synkk');
    assert.equal(matches[0].alias, 'Synkk Project');
    assert.equal(matches[1].target, 'Welcome');
    assert.equal(matches[1].alias, null);
});

test('Obsidian Callout syntax detection recognizes callout tags and types', () => {
    const lines = [
        '> [!NOTE] This is a note',
        '> [!TIP] Helpful recommendation',
        '> [!WARNING] Critical notice',
        '> [!DANGER] Extreme risk',
        'Regular blockquote line',
    ];

    const regex = /^>\s*\[!([a-zA-Z0-9_-]+)\](.*)$/;
    const results = lines.map(line => {
        const m = regex.exec(line);
        return m ? m[1].toUpperCase() : null;
    });

    assert.deepEqual(results, ['NOTE', 'TIP', 'WARNING', 'DANGER', null]);
});

test('EditorState manages markdown documents and dispatches transactions', () => {
    const state = EditorState.create({
        doc: '# Initial Note\n\nStarting text.',
        extensions: [markdown({ base: markdownLanguage })],
    });

    assert.equal(state.doc.toString(), '# Initial Note\n\nStarting text.');

    // Dispatch transaction replacing content
    const tr = state.update({
        changes: { from: 0, to: state.doc.length, insert: '# Modified Note\n\nUpdated text.' },
    });
    assert.equal(tr.state.doc.toString(), '# Modified Note\n\nUpdated text.');

    // Dispatch line prefix transaction (H2 heading)
    const line = tr.state.doc.line(1);
    const h2Tr = tr.state.update({
        changes: { from: line.from, to: line.from + 2, insert: '## ' },
    });
    assert.ok(h2Tr.state.doc.toString().startsWith('## Modified Note'));
});

test('two Y.Doc instances bound to CRDT converge deterministically on edits', () => {
    const docA = new Y.Doc();
    const docB = new Y.Doc();

    const ytextA = docA.getText('markdown');
    const ytextB = docB.getText('markdown');

    const awarenessA = new SynkkAwareness(docA);
    const awarenessB = new SynkkAwareness(docB);

    awarenessA.setLocalStateField('user', { name: 'Alice', color: '#10B981' });
    awarenessB.setLocalStateField('user', { name: 'Bob', color: '#8B5CF6' });

    // Synchronize updates between docA and docB
    docA.on('update', update => Y.applyUpdate(docB, update));
    docB.on('update', update => Y.applyUpdate(docA, update));

    ytextA.insert(0, '# Shared Architecture Document');
    assert.equal(ytextA.toString(), '# Shared Architecture Document');
    assert.equal(ytextB.toString(), '# Shared Architecture Document');

    // Alice appends text via Yjs transaction
    docA.transact(() => {
        ytextA.insert(ytextA.length, '\n\nSection 1: CRDT Collaboration.');
    });

    assert.equal(ytextA.toString(), ytextB.toString());
    assert.ok(ytextB.toString().includes('Section 1: CRDT Collaboration.'));

    // Bob prepends subtitle via Yjs
    docB.transact(() => {
        ytextB.insert(0, '> Co-authored by Alice and Bob\n\n');
    });

    assert.equal(ytextA.toString(), ytextB.toString());
    assert.ok(ytextA.toString().startsWith('> Co-authored by Alice and Bob'));

    awarenessA.destroy();
    awarenessB.destroy();
    docA.destroy();
    docB.destroy();
});
