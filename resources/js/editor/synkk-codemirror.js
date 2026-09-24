import {
    EditorView,
    keymap,
    lineNumbers,
    highlightActiveLineGutter,
    highlightActiveLine,
    Decoration,
    ViewPlugin,
} from '@codemirror/view';
import {
    EditorState,
    Compartment,
    Annotation,
    RangeSetBuilder,
} from '@codemirror/state';
import {
    defaultKeymap,
    history,
    historyKeymap,
    indentWithTab,
} from '@codemirror/commands';
import { markdown, markdownLanguage } from '@codemirror/lang-markdown';
import {
    syntaxHighlighting,
    HighlightStyle,
    bracketMatching,
} from '@codemirror/language';
import { tags as t } from '@lezer/highlight';
import { closeBrackets } from '@codemirror/autocomplete';

const remoteYTextSync = Annotation.define();

export function syncEditorContentToYText(ytext, content, origin) {
    if (!ytext?.doc || ytext.toString() === content) return;

    ytext.doc.transact(() => {
        ytext.delete(0, ytext.length);
        ytext.insert(0, content);
    }, origin);
}

/**
 * Bespoke Synkk Obsidian Dark Theme
 * Tuned to match Synkk's #0E1015 / #12151d aesthetic and Obsidian color tokens.
 */
export const synkkDarkTheme = EditorView.theme({
    '&': {
        color: '#f4f4f5',
        backgroundColor: '#0E1015',
        height: '100%',
        minHeight: '580px',
        fontSize: '13.5px',
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
    },
    '.cm-content': {
        caretColor: '#10B981',
        padding: '16px 20px',
        lineHeight: '1.65',
    },
    '&.cm-focused .cm-cursor': {
        borderLeftColor: '#10B981',
        borderLeftWidth: '2px',
    },
    '&.cm-focused .cm-selectionBackground, ::selection': {
        backgroundColor: 'rgba(16, 185, 129, 0.22) !important',
    },
    '.cm-gutters': {
        backgroundColor: '#0C0E13',
        color: '#52525b',
        borderRight: '1px solid #252836',
        paddingRight: '6px',
        userSelect: 'none',
    },
    '.cm-activeLineGutter': {
        backgroundColor: '#161822',
        color: '#a1a1aa',
        fontWeight: 'bold',
    },
    '.cm-activeLine': {
        backgroundColor: 'rgba(255, 255, 255, 0.025)',
    },
    '.cm-selectionMatch': {
        backgroundColor: 'rgba(147, 51, 234, 0.2)',
    },
    // y-codemirror remote selections & presence carets
    '.cm-ySelection': {
        borderRadius: '2px',
    },
    '.cm-ySelectionCaret': {
        position: 'relative',
        borderLeft: '2px solid black',
        borderRight: 'none',
        boxSizing: 'border-box',
        display: 'inline',
    },
    '.cm-ySelectionInfo': {
        position: 'absolute',
        top: '-1.35em',
        left: '-2px',
        fontSize: '10px',
        fontFamily: 'ui-sans-serif, system-ui, sans-serif',
        fontWeight: '700',
        lineHeight: '1',
        userSelect: 'none',
        color: '#ffffff',
        padding: '2px 5px',
        borderRadius: '4px',
        zIndex: '101',
        whiteSpace: 'nowrap',
        boxShadow: '0 2px 4px rgba(0,0,0,0.4)',
        letterSpacing: '0.02em',
    },
}, { dark: true });

/**
 * Syntax Highlighting Style for Markdown & Code
 */
export const synkkHighlightStyle = HighlightStyle.define([
    { tag: t.heading1, color: '#f43f5e', fontWeight: 'bold' },
    { tag: t.heading2, color: '#34d399', fontWeight: 'bold' },
    { tag: t.heading3, color: '#2dd4bf', fontWeight: 'bold' },
    { tag: t.heading4, color: '#38bdf8', fontWeight: 'bold' },
    { tag: t.strong, color: '#facc15', fontWeight: 'bold' },
    { tag: t.emphasis, color: '#e4e4e7', fontStyle: 'italic' },
    { tag: t.strikethrough, color: '#71717a', textDecoration: 'line-through' },
    { tag: t.keyword, color: '#c084fc' },
    { tag: t.atom, color: '#f472b6' },
    { tag: t.number, color: '#fb923c' },
    { tag: t.definition(t.variableName), color: '#38bdf8' },
    { tag: t.variableName, color: '#e4e4e7' },
    { tag: t.function(t.variableName), color: '#60a5fa' },
    { tag: t.string, color: '#a7f3d0' },
    { tag: t.comment, color: '#71717a', fontStyle: 'italic' },
    { tag: t.link, color: '#38bdf8', textDecoration: 'underline' },
    { tag: t.url, color: '#818cf8', textDecoration: 'underline' },
    { tag: t.monospace, color: '#38bdf8' },
]);

/**
 * Obsidian Wikilink Inline Syntax Highlighter
 * Matches [[Note Name]] and [[Target|Alias]]
 */
export const obsidianWikilinksPlugin = ViewPlugin.fromClass(class {
    constructor(view) {
        this.decorations = this.buildDecorations(view);
    }

    update(update) {
        if (update.docChanged || update.viewportChanged) {
            this.decorations = this.buildDecorations(update.view);
        }
    }

    buildDecorations(view) {
        const builder = new RangeSetBuilder();
        const regex = /\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g;

        for (const { from, to } of view.visibleRanges) {
            const text = view.state.doc.sliceString(from, to);
            let match;
            while ((match = regex.exec(text)) !== null) {
                const start = from + match.index;
                const end = start + match[0].length;
                builder.add(start, end, Decoration.mark({
                    class: 'cm-synkk-wikilink bg-emerald-500/10 text-emerald-400 font-semibold px-1 rounded hover:bg-emerald-500/20 cursor-pointer transition-colors',
                    attributes: {
                        'data-wikilink': match[1].trim(),
                        'title': `Obsidian Wikilink: ${match[1].trim()}`,
                    },
                }));
            }
        }

        return builder.finish();
    }
}, {
    decorations: v => v.decorations,
});

/**
 * Obsidian Callout Line/Block Decorator
 * Matches lines starting with `> [!TYPE]`
 */
export const obsidianCalloutPlugin = ViewPlugin.fromClass(class {
    constructor(view) {
        this.decorations = this.buildDecorations(view);
    }

    update(update) {
        if (update.docChanged || update.viewportChanged) {
            this.decorations = this.buildDecorations(update.view);
        }
    }

    buildDecorations(view) {
        const builder = new RangeSetBuilder();
        const regex = /^>\s*\[!([a-zA-Z0-9_-]+)\](.*)$/;

        for (const { from, to } of view.visibleRanges) {
            const doc = view.state.doc;
            const startLine = doc.lineAt(from).number;
            const endLine = doc.lineAt(to).number;

            for (let i = startLine; i <= endLine; i++) {
                const line = doc.line(i);
                const match = regex.exec(line.text);
                if (match) {
                    const calloutType = match[1].toLowerCase();
                    builder.add(line.from, line.from, Decoration.line({
                        class: `cm-synkk-callout-header cm-callout-${calloutType} bg-zinc-800/60 font-semibold text-emerald-300 pl-2 border-l-2 border-emerald-500`,
                    }));
                }
            }
        }

        return builder.finish();
    }
}, {
    decorations: v => v.decorations,
});

/**
 * Extract Headings from CodeMirror Document
 */
export function extractDocumentHeadings(doc) {
    const headings = [];
    const regex = /^(#{1,6})\s+(.+)$/;
    const lineCount = doc.lines;

    for (let i = 1; i <= lineCount; i++) {
        const line = doc.line(i);
        const match = regex.exec(line.text);
        if (match) {
            headings.push({
                level: match[1].length,
                title: match[2].trim(),
                line: i,
                pos: line.from,
            });
        }
    }

    return headings;
}

/**
 * Extract Document Text Metrics (Words, Characters, Lines)
 */
export function extractDocumentMetrics(doc) {
    const text = doc.toString();
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    const characters = text.length;
    const lineCount = doc.lines;

    return {
        words,
        characters,
        lineCount,
    };
}

/**
 * CodeMirror 6 Editor Instance Factory
 */
export function createSynkkEditor(container, {
    initialDoc = '',
    ytext = null,
    awareness = null,
    undoManager = null,
    canEdit = true,
    onChange = null,
    onSave = null,
} = {}) {
    const readOnlyCompartment = new Compartment();
    const editableCompartment = new Compartment();
    const collaborationOrigin = Symbol('synkk-codemirror-local');
    let localYTextSyncTimer = null;
    let view = null;

    // If Y.Text is provided and empty, seed with initialDoc
    if (ytext && ytext.length === 0 && initialDoc) {
        ytext.doc.transact(() => {
            ytext.insert(0, initialDoc);
        }, 'local');
    }

    const extensions = [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        bracketMatching(),
        closeBrackets(),
        synkkDarkTheme,
        syntaxHighlighting(synkkHighlightStyle),
        markdown({ base: markdownLanguage }),
        obsidianWikilinksPlugin,
        obsidianCalloutPlugin,
        readOnlyCompartment.of(EditorState.readOnly.of(!canEdit)),
        editableCompartment.of(EditorView.editable.of(canEdit)),
    ];

    // Collaboration is synchronized through a guarded Y.Text bridge below.
    // y-codemirror's observer dispatches against absolute positions while a
    // CodeMirror update may still be in flight, which can corrupt the view
    // when a durable snapshot and a local edit arrive together.
    extensions.push(history());

    // Keybindings
    const customKeymaps = [
        ...defaultKeymap,
        ...historyKeymap,
        indentWithTab,
    ];

    if (onSave) {
        customKeymaps.push({
            key: 'Mod-s',
            run: () => {
                onSave();
                return true;
            },
        });
    }

    extensions.push(keymap.of(customKeymaps));

    // Change Listener
    if (onChange) {
        extensions.push(EditorView.updateListener.of((update) => {
            if (update.docChanged) {
                const doc = update.state.doc;
                const isRemoteSync = update.transactions.some((transaction) => transaction.annotation(remoteYTextSync));

                if (ytext && !isRemoteSync) {
                    if (localYTextSyncTimer) clearTimeout(localYTextSyncTimer);
                    localYTextSyncTimer = setTimeout(() => {
                        syncEditorContentToYText(ytext, doc.toString(), collaborationOrigin);
                        localYTextSyncTimer = null;
                    }, 150);
                }

                const metrics = extractDocumentMetrics(doc);
                const headings = extractDocumentHeadings(doc);
                onChange({
                    content: doc.toString(),
                    metrics,
                    headings,
                    isDirty: true,
                });
            }
        }));
    }

    // Determine initial text for state
    const startingText = ytext ? ytext.toString() : initialDoc;

    const state = EditorState.create({
        doc: startingText,
        extensions,
    });

    view = new EditorView({
        state,
        parent: container,
    });

    const remoteYTextObserver = ytext
        ? (event, transaction) => {
            if (transaction.origin === collaborationOrigin) return;

            if (localYTextSyncTimer) {
                clearTimeout(localYTextSyncTimer);
                localYTextSyncTimer = null;
            }

            const incoming = ytext.toString();
            if (view.state.doc.toString() === incoming) return;

            view.dispatch({
                changes: { from: 0, to: view.state.doc.length, insert: incoming },
                annotations: remoteYTextSync.of(true),
            });
        }
        : null;

    remoteYTextObserver && ytext.observe(remoteYTextObserver);

    /**
     * Wrap selection with prefix and suffix (e.g. bold, italic)
     */
    function wrapSelection(prefix, suffix, defaultText = 'text') {
        if (!canEdit) return;
        const state = view.state;
        const changes = state.changeByRange((range) => {
            const selected = state.sliceDoc(range.from, range.to);
            const text = selected || defaultText;
            const replacement = `${prefix}${text}${suffix}`;

            return {
                changes: { from: range.from, to: range.to, insert: replacement },
                range: selected
                    ? EditorSelection.range(range.from + prefix.length, range.to + prefix.length)
                    : EditorSelection.range(range.from + prefix.length, range.from + prefix.length + text.length),
            };
        });

        view.dispatch(changes);
        view.focus();
    }

    /**
     * Prepend line prefix (e.g. heading, list, blockquote)
     */
    function toggleLinePrefix(prefix) {
        if (!canEdit) return;
        const state = view.state;
        const changes = state.changeByRange((range) => {
            const line = state.doc.lineAt(range.from);
            let newText;

            if (line.text.startsWith(prefix)) {
                // Remove prefix if already present
                newText = line.text.substring(prefix.length);
                return {
                    changes: { from: line.from, to: line.to, insert: newText },
                    range: EditorSelection.cursor(Math.max(line.from, range.from - prefix.length)),
                };
            } else if (/^#{1,6}\s+/.test(line.text) && /^#{1,6}\s+/.test(prefix)) {
                // Replace existing heading level
                newText = line.text.replace(/^#{1,6}\s+/, prefix);
                return {
                    changes: { from: line.from, to: line.to, insert: newText },
                    range: EditorSelection.cursor(line.from + prefix.length),
                };
            } else {
                // Prepend prefix
                newText = prefix + line.text;
                return {
                    changes: { from: line.from, to: line.to, insert: newText },
                    range: EditorSelection.cursor(range.from + prefix.length),
                };
            }
        });

        view.dispatch(changes);
        view.focus();
    }

    /**
     * Insert text at current cursor / selection
     */
    function insertSnippet(snippet, selectOffset = null, selectLength = 0) {
        if (!canEdit) return;
        const range = view.state.selection.main;
        view.dispatch({
            changes: { from: range.from, to: range.to, insert: snippet },
            selection: selectOffset !== null
                ? { anchor: range.from + selectOffset, head: range.from + selectOffset + selectLength }
                : { anchor: range.from + snippet.length },
        });
        view.focus();
    }

    // Helper for EditorSelection
    const EditorSelection = {
        cursor(pos) {
            return { anchor: pos, head: pos };
        },
        range(anchor, head) {
            return { anchor, head };
        },
    };

    return {
        view,

        getContent() {
            return view.state.doc.toString();
        },

        setContent(text) {
            if (view.state.doc.toString() === text) return;
            view.dispatch({
                changes: { from: 0, to: view.state.doc.length, insert: text },
            });
        },

        focus() {
            view.focus();
        },

        destroy() {
            if (localYTextSyncTimer) clearTimeout(localYTextSyncTimer);
            remoteYTextObserver && ytext.unobserve(remoteYTextObserver);
            view.destroy();
        },

        setReadOnly(readOnly) {
            canEdit = !readOnly;
            view.dispatch({
                effects: [
                    readOnlyCompartment.reconfigure(EditorState.readOnly.of(readOnly)),
                    editableCompartment.reconfigure(EditorView.editable.of(!readOnly)),
                ],
            });
        },

        scrollToHeading(heading) {
            if (heading && typeof heading.pos === 'number') {
                view.dispatch({
                    selection: { anchor: heading.pos },
                    scrollIntoView: true,
                });
                view.focus();
            }
        },

        // Toolbar Command Dispatchers
        bold() {
            wrapSelection('**', '**', 'bold text');
        },

        italic() {
            wrapSelection('*', '*', 'italic text');
        },

        strikethrough() {
            wrapSelection('~~', '~~', 'strikethrough text');
        },

        heading(level = 1) {
            toggleLinePrefix('#'.repeat(Math.max(1, Math.min(6, level))) + ' ');
        },

        bulletList() {
            toggleLinePrefix('- ');
        },

        numberedList() {
            toggleLinePrefix('1. ');
        },

        taskList() {
            toggleLinePrefix('- [ ] ');
        },

        link() {
            wrapSelection('[', '](https://)', 'Link Title');
        },

        image() {
            wrapSelection('![', '](image.png)', 'Alt Text');
        },

        wikilink() {
            wrapSelection('[[', ']]', 'Wiki Note Name');
        },

        inlineCode() {
            wrapSelection('`', '`', 'code');
        },

        codeBlock(lang = 'javascript') {
            insertSnippet(`\`\`\`${lang}\n// your code here\n\`\`\`\n`, 3 + lang.length + 1, 17);
        },

        table() {
            const tableSnippet = '\n| Header 1 | Header 2 | Header 3 |\n| :--- | :--- | :--- |\n| Cell 1 | Cell 2 | Cell 3 |\n';
            insertSnippet(tableSnippet);
        },

        callout(type = 'TIP') {
            const calloutSnippet = `> [!${type.toUpperCase()}]\n> Add your callout content here\n`;
            insertSnippet(calloutSnippet, 5 + type.length + 2, 28);
        },

        horizontalRule() {
            insertSnippet('\n---\n');
        },
    };
}
