import * as Y from 'yjs';
import { yCollab } from 'y-codemirror.next';
import { SynkkYjsProvider, SynkkAwareness } from './collaboration/yjs-provider.js';
import { createSynkkEditor } from './editor/synkk-codemirror.js';

const getVaultCrypto = () => (typeof window !== 'undefined' && window.VaultCrypto ? window.VaultCrypto : null);

const escapeHtml = (value = '') => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const safeHref = (value = '') => {
    const href = String(value).trim();

    if (/^(https?:|mailto:)/i.test(href) || /^(#|\/|\.\/|\.\.\/)/.test(href)) {
        return escapeHtml(href);
    }

    return '#';
};

const CALLOUT_ALIASES = {
    note: 'note',
    seealso: 'note',
    abstract: 'abstract',
    summary: 'abstract',
    tldr: 'abstract',
    info: 'info',
    todo: 'todo',
    tip: 'tip',
    hint: 'tip',
    important: 'important',
    success: 'success',
    check: 'success',
    done: 'success',
    question: 'question',
    help: 'question',
    faq: 'question',
    warning: 'warning',
    caution: 'warning',
    attention: 'warning',
    failure: 'failure',
    fail: 'failure',
    missing: 'failure',
    danger: 'danger',
    error: 'danger',
    bug: 'bug',
    example: 'example',
    quote: 'quote',
    cite: 'quote',
};

const CALLOUT_ICONS = {
    note: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path d="M11.013 1.427a1.75 1.75 0 012.474 0l1.086 1.086a1.75 1.75 0 010 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 01-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61zm1.414 1.06a.25.25 0 00-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 000-.354l-1.086-1.086zM9.75 4.81l-6.97 6.97-.62 2.17 2.17-.62 6.97-6.97-1.55-1.55z"/></svg>',
    abstract: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 3A1.5 1.5 0 001 4.5v7A1.5 1.5 0 002.5 13h11a1.5 1.5 0 001.5-1.5v-7A1.5 1.5 0 0013.5 3h-11zm1 2.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zm0 3a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zm0 3a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>',
    info: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M15 8A7 7 0 111 8a7 7 0 0114 0zM9 5a1 1 0 11-2 0 1 1 0 012 0zM6.75 8a.75.75 0 000 1.5h.75v2.25a.75.75 0 001.5 0v-3A.75.75 0 008.25 8h-1.5z" clip-rule="evenodd"/></svg>',
    todo: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 01.208 1.04l-5 7.5a.75.75 0 01-1.154.114l-3-3a.75.75 0 011.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 011.04-.207z" clip-rule="evenodd"/></svg>',
    tip: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path d="M8 1.5a4.5 4.5 0 00-2.457 8.274c.48.33.784.887.807 1.476a.75.75 0 00.75.75h1.8a.75.75 0 00.75-.75c.023-.589.327-1.146.807-1.476A4.5 4.5 0 008 1.5zM6.5 13.5a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75z"/></svg>',
    important: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM7.25 5a.75.75 0 011.5 0v4a.75.75 0 01-1.5 0V5zm.75 7.25a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
    success: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M16 8A8 8 0 110 8a8 8 0 0116 0zm-3.846-2.469a.75.75 0 00-1.06-1.061L6.75 8.818 4.906 6.975a.75.75 0 00-1.06 1.06l2.375 2.376a.75.75 0 001.06 0l5.873-5.88z" clip-rule="evenodd"/></svg>',
    question: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM6.5 6a1.5 1.5 0 113 0c0 .59-.444 1.002-.87 1.343-.377.301-.88.703-.88 1.407a.75.75 0 001.5 0c0-.18.172-.34.45-.562.483-.385 1.3-.984 1.3-2.188A3 3 0 005 6a.75.75 0 001.5 0zM8 12.25a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
    warning: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0114.082 15H1.918a1.75 1.75 0 01-1.543-2.575L6.457 1.047zM8 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 018 5zm0 7.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
    failure: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M8 15A7 7 0 108 1a7 7 0 000 14zm2.78-9.72a.75.75 0 00-1.06-1.06L8 5.94 6.28 4.22a.75.75 0 00-1.06 1.06L6.94 7 5.22 8.72a.75.75 0 001.06 1.06L8 8.06l1.72 1.72a.75.75 0 001.06-1.06L9.06 7l1.72-1.72z" clip-rule="evenodd"/></svg>',
    danger: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 1.754a.75.75 0 00-1.063-.564L2.83 3.064A1.75 1.75 0 001.75 4.708v4.295a7.5 7.5 0 004.28 6.728l1.493.746a.75.75 0 00.672 0l1.493-.746a7.5 7.5 0 004.28-6.728V4.708a1.75 1.75 0 00-1.08-1.644L8.22 1.754zM8 5a.75.75 0 01.75.75v2.5a.75.75 0 01-1.5 0v-2.5A.75.75 0 018 5zm0 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
    bug: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path d="M4.72 3.22a.75.75 0 011.06 1.06L4.81 5.25h6.38l-.97-.97a.75.75 0 111.06-1.06l2.25 2.25a.75.75 0 010 1.06l-2.25 2.25a.75.75 0 01-1.06-1.06l.97-.97H4.81l.97.97a.75.75 0 11-1.06 1.06L2.47 6.53a.75.75 0 010-1.06l2.25-2.25zM2 10.75a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm1.5 3a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75z"/></svg>',
    example: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 1.5a.75.75 0 000 1.5h1.104l1.64 6.562A2.75 2.75 0 007.82 11.75h4.43a2.75 2.75 0 002.576-1.78l1.09-3.27a.75.75 0 00-.712-.987H5.056L4.68 4.25H13.5a.75.75 0 000-1.5H4.25a.75.75 0 00-.728.568L3.146 5H2.5zm5.32 8.75a1.25 1.25 0 01-1.216-.945L5.43 5.5h8.902l-.833 2.5a1.25 1.25 0 01-1.171.81H7.82zm-1.07 3.5a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zm6.5 0a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0z" clip-rule="evenodd"/></svg>',
    quote: '<svg viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M1.75 2.5a.75.75 0 000 1.5h12.5a.75.75 0 000-1.5H1.75zm0 5a.75.75 0 000 1.5h12.5a.75.75 0 000-1.5H1.75zm0 5a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5z" clip-rule="evenodd"/></svg>',
};

const renderInlineMarkdown = (value = '') => {
    const tokens = [];
    const storeToken = (html) => {
        const key = `\u0000SYNKK${tokens.length}\u0000`;
        tokens.push(html);

        return key;
    };

    let output = String(value);

    output = output.replace(/`([^`]+)`/g, (_, code) => storeToken(`<code>${escapeHtml(code)}</code>`));
    output = output.replace(/\$([^\$\n]+)\$/g, (_, math) => storeToken(`<span class="synkk-math-inline"><span class="synkk-math-symbol">fx</span><code>${escapeHtml(math.trim())}</code></span>`));
    output = output.replace(/==([^=\n]+)==/g, (_, mark) => storeToken(`<mark class="synkk-highlight">${escapeHtml(mark)}</mark>`));
    output = output.replace(/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g, (_, target, label) => {
        const display = label?.trim() || target.trim();

        return storeToken(`<span class="synkk-wiki-link">${escapeHtml(display)}</span>`);
    });
    output = output.replace(/\[([^\]]+)]\(([^)]+)\)/g, (_, label, href) => {
        return storeToken(`<a href="${safeHref(href)}" target="_blank" rel="noreferrer noopener">${escapeHtml(label)}</a>`);
    });

    output = escapeHtml(output)
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/~~([^~]+)~~/g, '<del>$1</del>')
        .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');

    tokens.forEach((token, index) => {
        output = output.replace(`\u0000SYNKK${index}\u0000`, token);
    });

    return output;
};

const isTableDivider = (line = '') => /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(line);

const tableCells = (line = '') => line
    .trim()
    .replace(/^\|/, '')
    .replace(/\|$/, '')
    .split('|')
    .map((cell) => cell.trim());

const startsBlock = (lines, index) => {
    const line = lines[index] || '';
    const trimmed = line.trim();
    const next = lines[index + 1] || '';

    return trimmed === ''
        || /^#{1,6}\s+/.test(line)
        || /^```/.test(trimmed)
        || /^\$\$/.test(trimmed)
        || /^>\s?/.test(line)
        || /^[-*+]\s+/.test(line)
        || /^\d+\.\s+/.test(line)
        || /^(---|\*\*\*|___)$/.test(trimmed)
        || (line.includes('|') && isTableDivider(next));
};

export function renderMarkdownPreview(markdown = '') {
    const lines = String(markdown).replaceAll('\r\n', '\n').split('\n');
    const html = [];

    for (let index = 0; index < lines.length;) {
        const line = lines[index];
        const trimmed = line.trim();

        if (trimmed === '') {
            index += 1;
            continue;
        }

        // Block Math: $$ ... $$
        if (trimmed.startsWith('$$')) {
            const math = [];
            if (trimmed.length > 2 && trimmed.endsWith('$$') && trimmed !== '$$') {
                math.push(trimmed.slice(2, -2).trim());
                index += 1;
            } else {
                if (trimmed.length > 2) {
                    math.push(trimmed.slice(2).trim());
                }
                index += 1;
                while (index < lines.length && !lines[index].trim().endsWith('$$')) {
                    math.push(lines[index]);
                    index += 1;
                }
                if (index < lines.length) {
                    const endLine = lines[index].trim();
                    if (endLine.length > 2) {
                        math.push(endLine.slice(0, -2).trim());
                    }
                    index += 1;
                }
            }
            html.push(`<div class="synkk-math-block"><div class="synkk-math-badge">LaTeX Math</div><code>${escapeHtml(math.join('\n').trim())}</code></div>`);
            continue;
        }

        const fence = trimmed.match(/^```([^\s]*)/);
        if (fence) {
            const lang = (fence[1] || '').toLowerCase();
            const code = [];
            index += 1;

            while (index < lines.length && !lines[index].trim().startsWith('```')) {
                code.push(lines[index]);
                index += 1;
            }

            if (index < lines.length) {
                index += 1;
            }

            const rawCode = code.join('\n');

            if (lang === 'mermaid') {
                html.push(`<div class="synkk-mermaid-block" data-diagram="mermaid"><div class="synkk-diagram-header"><span class="synkk-diagram-badge"><svg viewBox="0 0 16 16" fill="currentColor" class="size-3.5" aria-hidden="true"><path d="M1 3.5A2.5 2.5 0 013.5 1h9A2.5 2.5 0 0115 3.5v9a2.5 2.5 0 01-2.5 2.5h-9A2.5 2.5 0 011 12.5v-9zM3.5 2A1.5 1.5 0 002 3.5v9A1.5 1.5 0 003.5 14h9a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0012.5 2h-9z"/></svg> MERMAID DIAGRAM</span></div><pre class="synkk-mermaid-source"><code>${escapeHtml(rawCode)}</code></pre></div>`);
                continue;
            }

            if (lang === 'dataview' || lang === 'dataviewjs') {
                const firstWord = rawCode.trim().split(/\s+/)[0]?.toUpperCase() || 'QUERY';
                html.push(`<div class="synkk-dataview-block"><div class="synkk-dataview-header"><span class="synkk-dataview-badge">DATAVIEW</span><span class="synkk-dataview-type">${escapeHtml(firstWord)}</span></div><div class="synkk-dataview-content"><pre><code>${escapeHtml(rawCode)}</code></pre></div></div>`);
                continue;
            }

            if (lang === 'kanban') {
                const columns = [];
                let currentCol = null;
                for (const cline of code) {
                    const ctrim = cline.trim();
                    if (ctrim.startsWith('## ') || ctrim.startsWith('# ')) {
                        currentCol = { title: ctrim.replace(/^#+\s+/, ''), cards: [] };
                        columns.push(currentCol);
                    } else if (currentCol && /^[-*+]\s+/.test(ctrim)) {
                        currentCol.cards.push(ctrim.replace(/^[-*+]\s+(\[[ xX]])?\s*/, ''));
                    }
                }
                if (columns.length > 0) {
                    html.push(`<div class="synkk-kanban-board">${columns.map((col) => `<div class="synkk-kanban-col"><div class="synkk-kanban-col-header"><strong>${escapeHtml(col.title)}</strong><span class="synkk-kanban-count">${col.cards.length}</span></div><div class="synkk-kanban-cards">${col.cards.map((card) => `<div class="synkk-kanban-card">${renderInlineMarkdown(card)}</div>`).join('')}</div></div>`).join('')}</div>`);
                    continue;
                }
            }

            const language = lang ? ` data-language="${escapeHtml(lang)}"` : '';
            html.push(`<pre${language}><code>${escapeHtml(rawCode)}</code></pre>`);
            continue;
        }

        const heading = line.match(/^(#{1,6})\s+(.+)$/);
        if (heading) {
            const level = heading[1].length;
            html.push(`<h${level}>${renderInlineMarkdown(heading[2])}</h${level}>`);
            index += 1;
            continue;
        }

        const callout = line.match(/^>\s*\[!([a-zA-Z_-]+)]\s*(.*)$/);
        if (callout) {
            const rawType = callout[1].toLowerCase();
            const canonicalType = CALLOUT_ALIASES[rawType] || 'note';
            const iconSvg = CALLOUT_ICONS[canonicalType] || CALLOUT_ICONS.note;
            const title = callout[2].trim() || rawType.replace(/^./, (letter) => letter.toUpperCase());
            const body = [];
            index += 1;

            while (index < lines.length && /^>\s?/.test(lines[index])) {
                body.push(lines[index].replace(/^>\s?/, ''));
                index += 1;
            }

            html.push(`<aside class="synkk-callout synkk-callout--${canonicalType}"><div class="synkk-callout-header"><span class="synkk-callout-icon">${iconSvg}</span><strong>${escapeHtml(title)}</strong></div><p>${renderInlineMarkdown(body.join(' '))}</p></aside>`);
            continue;
        }

        if (/^>\s?/.test(line)) {
            const quote = [];

            while (index < lines.length && /^>\s?/.test(lines[index])) {
                quote.push(lines[index].replace(/^>\s?/, ''));
                index += 1;
            }

            html.push(`<blockquote>${renderInlineMarkdown(quote.join(' '))}</blockquote>`);
            continue;
        }

        if (line.includes('|') && isTableDivider(lines[index + 1] || '')) {
            const headers = tableCells(line);
            const rows = [];
            index += 2;

            while (index < lines.length && lines[index].includes('|') && lines[index].trim() !== '') {
                rows.push(tableCells(lines[index]));
                index += 1;
            }

            html.push(`<div class="synkk-table-wrap"><table><thead><tr>${headers.map((cell) => `<th>${renderInlineMarkdown(cell)}</th>`).join('')}</tr></thead><tbody>${rows.map((row) => `<tr>${row.map((cell) => `<td>${renderInlineMarkdown(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`);
            continue;
        }

        if (/^[-*+]\s+/.test(line)) {
            const items = [];

            while (index < lines.length && /^[-*+]\s+/.test(lines[index])) {
                const item = lines[index].replace(/^[-*+]\s+/, '');
                const task = item.match(/^\[([ xX])]\s*(.*)$/);
                items.push(task
                    ? `<li class="synkk-task"><input type="checkbox" disabled ${task[1].toLowerCase() === 'x' ? 'checked' : ''}> <span>${renderInlineMarkdown(task[2])}</span></li>`
                    : `<li>${renderInlineMarkdown(item)}</li>`);
                index += 1;
            }

            html.push(`<ul>${items.join('')}</ul>`);
            continue;
        }

        if (/^\d+\.\s+/.test(line)) {
            const items = [];

            while (index < lines.length && /^\d+\.\s+/.test(lines[index])) {
                items.push(`<li>${renderInlineMarkdown(lines[index].replace(/^\d+\.\s+/, ''))}</li>`);
                index += 1;
            }

            html.push(`<ol>${items.join('')}</ol>`);
            continue;
        }

        if (/^(---|\*\*\*|___)$/.test(trimmed)) {
            html.push('<hr>');
            index += 1;
            continue;
        }

        const paragraph = [trimmed];
        index += 1;

        while (index < lines.length && !startsBlock(lines, index)) {
            paragraph.push(lines[index].trim());
            index += 1;
        }

        html.push(`<p>${renderInlineMarkdown(paragraph.join(' '))}</p>`);
    }

    return html.join('') || '<p class="synkk-empty-note">Empty note. Start typing to preview…</p>';
}

export function createMarkdownEditor(options = {}) {
    const content = String(options.content || '');

    return {
        activeFileId: options.activeFileId ?? null,
        documentId: options.documentId ?? null,
        filePath: options.filePath ?? '',
        content,
        viewMode: options.viewMode || 'split',
        canEdit: Boolean(options.canEdit),
        sidebarOpen: true,
        lineCount: 1,
        wordCount: 0,
        charCount: 0,
        headings: [],
        lineTypes: [],
        previewHtml: renderMarkdownPreview(content),
        isDirty: Boolean(options.initiallyDirty),
        initialContent: options.initiallyDirty ? null : content,
        activeHeading: '',
        shareStatus: 'idle',
        quickInsertOpen: false,
        isSaving: false,
        saveFailed: false,
        beforeUnloadHandler: null,
        editorInstance: null,

        // CRDT / Yjs Collaboration State
        user: options.user || { id: null, name: 'Web Editor', color: '#10B981' },
        collabEnabled: options.collabEnabled ?? Boolean(options.documentId || options.filePath),
        snapshotDebounceMs: options.snapshotDebounceMs || 2500,
        ydoc: null,
        ytext: null,
        undoManager: null,
        provider: null,
        awareness: null,
        activePeers: [],
        snapshotTimer: null,
        isApplyingRemote: false,
        collabExtension: null,

        // E2EE Zero-Knowledge State
        vaultSlug: options.vaultSlug || '',
        isEncrypted: Boolean(options.isEncrypted),
        encryptionIv: options.encryptionIv || '',
        encryptionTag: options.encryptionTag || '',
        vaultSalt: options.vaultSalt || '',
        vaultTestCipher: options.vaultTestCipher || '',
        isUnlocked: !Boolean(options.isEncrypted) || Boolean(getVaultCrypto()?.hasSessionKey?.(options.vaultSlug || '')),
        passphraseInput: '',
        unlockError: '',
        isDerivingKey: false,

        async init() {
            if (typeof window !== 'undefined' && window.innerWidth < 1024 && this.viewMode === 'split') {
                this.viewMode = 'source';
            }

            if (this.isEncrypted && getVaultCrypto()?.hasSessionKey?.(this.vaultSlug)) {
                await this.decryptCurrentNote();
            } else {
                this.updateMetrics();
            }

            if (this.collabEnabled && this.isUnlocked) {
                await this.initCollaboration();
            }

            // Mount CodeMirror 6 Collaborative Editor
            if (this.isUnlocked) {
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => this.mountEditorView());
                } else {
                    this.mountEditorView();
                }
            }

            this.$watch('content', (value) => {
                this.updateMetrics();
                if (this.isApplyingRemote) {
                    this.initialContent = value;
                    this.isDirty = false;
                } else {
                    this.isDirty = this.initialContent === null || value !== this.initialContent;
                }

                // CRITICAL E2EE SECURITY: Never assign plaintext to Livewire for encrypted notes!
                if (this.$wire) {
                    if (!this.isEncrypted) {
                        this.$wire.editorContent = value;
                    }
                    this.$wire.editorIsDirty = this.isDirty;
                }

                // Sync local content changes to Yjs
                if (this.ytext && !this.editorInstance && !this.isApplyingRemote && value !== this.ytext.toString()) {
                    this.ydoc.transact(() => {
                        this.ytext.delete(0, this.ytext.length);
                        this.ytext.insert(0, value);
                    }, 'local');
                }

                // When collaboration is active, y-codemirror owns the editor
                // document and applies Y.Text changes to the CodeMirror view.
                // Dispatching a second full-document replacement here can run
                // while CodeMirror is processing the Yjs transaction.
                if (this.editorInstance && !this.ytext && this.editorInstance.getContent() !== value) {
                    this.editorInstance.setContent(value);
                }

                if (this.canEdit && this.isDirty && !this.isApplyingRemote) {
                    this.scheduleSnapshotFlush();
                }
            });

            this.$watch('canEdit', (val) => {
                if (this.editorInstance) {
                    this.editorInstance.setReadOnly(!val);
                }
            });

            this.beforeUnloadHandler = (event) => {
                if (!this.isDirty) return;
                event.preventDefault();
                event.returnValue = '';
            };

            if (typeof window !== 'undefined') {
                window.addEventListener('beforeunload', this.beforeUnloadHandler);
            }
        },

        mountEditorView() {
            const container = this.$refs?.editorContainer;
            if (!container) return;

            if (this.editorInstance) {
                this.editorInstance.destroy();
                this.editorInstance = null;
            }

            const self = this;
            this.editorInstance = createSynkkEditor(container, {
                initialDoc: this.content || '',
                ytext: this.collabEnabled && this.isUnlocked ? this.ytext : null,
                awareness: this.collabEnabled && this.isUnlocked ? this.awareness : null,
                undoManager: this.collabEnabled && this.isUnlocked ? this.undoManager : null,
                canEdit: this.canEdit,
                onSave: () => {
                    if (self.canEdit && self.isDirty && !self.isSaving) {
                        self.saveEditor();
                    }
                },
                onChange: ({ content, metrics, headings }) => {
                    self.content = content;
                    self.lineCount = metrics.lineCount;
                    self.charCount = metrics.characters;
                    self.wordCount = metrics.words;
                    self.readTimeMinutes = Math.max(1, Math.ceil(metrics.words / 200));
                    self.headings = headings;
                    self.previewHtml = renderMarkdownPreview(content);
                    self.isDirty = self.initialContent === null || content !== self.initialContent;

                    if (self.$wire) {
                        if (!self.isEncrypted) {
                            self.$wire.editorContent = content;
                        }
                        self.$wire.editorIsDirty = self.isDirty;
                    }

                    if (self.canEdit && self.isDirty) {
                        self.scheduleSnapshotFlush();
                    }
                },
            });
        },

        async initCollaboration() {
            if (!this.collabEnabled) return;
            this.cleanupCollaboration();

            this.ydoc = new Y.Doc();
            this.ytext = this.ydoc.getText('markdown');
            this.undoManager = new Y.UndoManager(this.ytext);
            this.awareness = new SynkkAwareness(this.ydoc);

            this.awareness.setLocalStateField('user', {
                id: this.user.id || null,
                name: this.user.name || 'Web Editor',
                color: this.user.color || '#10B981',
            });

            this.awareness.on('change', () => {
                this.activePeers = Array.from(this.awareness.getStates().entries())
                    .filter(([clientId]) => clientId !== this.awareness.clientID)
                    .map(([clientId, state]) => ({
                        peer_id: String(clientId),
                        name: state.user?.name || 'Collaborator',
                        color: state.user?.color || '#10B981',
                        cursor: state.cursor || null,
                    }));
            });

            // Observe remote Yjs changes
            this.ytext.observe((event, transaction) => {
                const isRemoteTransaction = transaction.origin === this.provider?.providerOrigin
                    || transaction.origin === 'remote'
                    || transaction.origin === undefined
                    || transaction.origin === null;

                if (isRemoteTransaction) {
                    this.isApplyingRemote = true;
                    try {
                        const incoming = this.ytext.toString();
                        this.content = incoming;
                        this.initialContent = incoming;
                        this.isDirty = false;
                        this.updateMetrics();
                    } finally {
                        this.isApplyingRemote = false;
                    }
                }
            });

            // If editorView is provided, bind yCollab extension
            if (options.editorView) {
                this.collabExtension = yCollab(this.ytext, this.awareness, { undoManager: this.undoManager });
            }

            let cryptoKey = null;
            if (this.isEncrypted) {
                cryptoKey = getVaultCrypto()?.getSessionKey?.(this.vaultSlug) || null;
            }

            const self = this;
            this.provider = new SynkkYjsProvider({
                doc: this.ydoc,
                vaultId: this.vaultSlug,
                documentId: this.documentId,
                path: this.filePath,
                cryptoKey,
                echo: typeof window !== 'undefined' ? window.Echo : null,
                awareness: this.awareness,
                transport: {
                    fetchUpdates: async (sinceSeq) => {
                        if (!self.vaultSlug || typeof fetch === 'undefined') return { updates: [] };
                        try {
                            const res = await fetch(`/api/v1/vaults/${self.vaultSlug}/collab/catch-up?document_id=${self.documentId}&after_sequence=${sinceSeq}`, {
                                headers: { Accept: 'application/json' },
                            });
                            return await res.json();
                        } catch {
                            return { updates: [] };
                        }
                    },
                    sendUpdate: async (payload) => {
                        if (!self.vaultSlug || typeof fetch === 'undefined') return { status: 'mocked' };
                        const csrf = typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';
                        try {
                            const res = await fetch(`/api/v1/vaults/${self.vaultSlug}/collab/append`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify({
                                    document_id: self.documentId,
                                    path: self.filePath,
                                    ...payload,
                                }),
                            });
                            return await res.json();
                        } catch {
                            return { status: 'failed' };
                        }
                    },
                    appendUpdate: async (payload) => {
                        if (!self.vaultSlug || typeof fetch === 'undefined') return { status: 'mocked' };
                        const csrf = typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';
                        try {
                            const res = await fetch(`/api/v1/vaults/${self.vaultSlug}/collab/append`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify({
                                    document_id: self.documentId,
                                    path: self.filePath,
                                    ...payload,
                                }),
                            });
                            return await res.json();
                        } catch {
                            return { status: 'failed' };
                        }
                    },
                    checkpoint: async (payload) => {
                        if (!self.vaultSlug || typeof fetch === 'undefined') return { status: 'mocked' };
                        const csrf = typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';
                        try {
                            const res = await fetch(`/api/v1/vaults/${self.vaultSlug}/collab/checkpoint`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify({
                                    document_id: self.documentId,
                                    path: self.filePath,
                                    client_update_id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : 'ckpt-' + Date.now(),
                                    acknowledged_base_sequence: self.provider?.latestSequence || 0,
                                    payload: payload.checkpoint_snapshot,
                                    encrypted: payload.is_encrypted,
                                    iv: payload.encryption_iv,
                                    tag: payload.encryption_tag,
                                }),
                            });
                            return await res.json();
                        } catch {
                            return { status: 'failed' };
                        }
                    },
                },
            });

            await this.provider.connect();

            // Catch up from the durable journal before seeding a new document.
            // Seeding first would make every client insert the initial snapshot
            // with a different Yjs client id, duplicating the note when the
            // server later returns the already-persisted snapshot.
            if (this.ytext.length === 0 && this.content) {
                this.ydoc.transact(() => {
                    this.ytext.insert(0, this.content);
                }, 'local');
            } else if (this.ytext.length > 0 && this.content !== this.ytext.toString()) {
                this.content = this.ytext.toString();
                this.initialContent = this.content;
                this.isDirty = false;
                this.updateMetrics();
            }
        },

        scheduleSnapshotFlush() {
            if (!this.canEdit) return; // READ-ONLY NEVER BECOMES SNAPSHOT WRITER
            if (this.snapshotTimer) {
                clearTimeout(this.snapshotTimer);
            }
            this.snapshotTimer = setTimeout(async () => {
                if (this.isDirty && !this.isSaving) {
                    await this.saveEditor();
                }
            }, this.snapshotDebounceMs);
        },

        cleanupCollaboration() {
            if (this.editorInstance) {
                this.editorInstance.destroy();
                this.editorInstance = null;
            }
            if (this.snapshotTimer) {
                clearTimeout(this.snapshotTimer);
                this.snapshotTimer = null;
            }
            if (this.provider) {
                this.provider.destroy();
                this.provider = null;
            }
            if (this.awareness) {
                this.awareness.destroy();
                this.awareness = null;
            }
            if (this.undoManager) {
                this.undoManager.destroy();
                this.undoManager = null;
            }
            if (this.ydoc) {
                this.ydoc.destroy();
                this.ydoc = null;
                this.ytext = null;
            }
            this.activePeers = [];
        },

        lockPassphrase() {
            getVaultCrypto()?.clearSessionKey?.(this.vaultSlug);
            this.cleanupCollaboration();
            this.isUnlocked = false;
            this.passphraseInput = '';
            if (this.isEncrypted && this.initialContent) {
                this.content = this.initialContent;
            }
        },

        async decryptCurrentNote() {
            const vc = getVaultCrypto();
            if (!this.isEncrypted || !vc || typeof vc.decryptText !== 'function') return;
            const key = vc.getSessionKey?.(this.vaultSlug);
            if (!key) return;

            try {
                const plaintext = await vc.decryptText(
                    this.content,
                    this.encryptionIv,
                    this.encryptionTag,
                    key
                );
                this.content = plaintext;
                this.initialContent = plaintext;
                this.isUnlocked = true;
                this.updateMetrics();
            } catch (err) {
                console.error('Failed to decrypt note:', err);
                this.unlockError = 'Failed to decrypt note with session key.';
                this.isUnlocked = false;
            }
        },

        async unlockWithPassphrase() {
            const vc = getVaultCrypto();
            if (!this.passphraseInput || this.isDerivingKey || !vc || typeof vc.verifyPassphrase !== 'function') return;
            this.isDerivingKey = true;
            this.unlockError = '';

            try {
                const result = await vc.verifyPassphrase(
                    this.passphraseInput,
                    this.vaultSalt,
                    this.vaultTestCipher
                );

                if (!result.success) {
                    this.unlockError = result.error || 'Incorrect passphrase.';
                    return;
                }

                vc.setSessionKey(this.vaultSlug, result.key);
                this.isUnlocked = true;
                this.passphraseInput = '';

                if (this.content && this.encryptionIv) {
                    await this.decryptCurrentNote();
                }

                if (this.collabEnabled) {
                    await this.initCollaboration();
                }

                this.mountEditorView();
            } catch (err) {
                this.unlockError = err.message || 'Key derivation failed.';
            } finally {
                this.isDerivingKey = false;
            }
        },

        destroy() {
            this.cleanupCollaboration();
            if (typeof window !== 'undefined' && this.beforeUnloadHandler) {
                window.removeEventListener('beforeunload', this.beforeUnloadHandler);
            }
        },

        updateMetrics() {
            const text = this.content || '';
            const lines = text.split('\n');
            this.lineCount = Math.max(1, lines.length);
            this.charCount = text.length;
            const readableText = text
                .replace(/```[\s\S]*?```/g, ' ')
                .replace(/^\s{0,3}(?:#{1,6}|[-*+]|\d+\.)\s+/gm, '')
                .replace(/\[([^\]]+)]\([^)]+\)/g, '$1')
                .replace(/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g, (_, target, label) => label || target)
                .replace(/[*_~`>|]/g, ' ')
                .trim();
            this.wordCount = readableText ? readableText.split(/\s+/).length : 0;
            this.lineTypes = lines.slice(0, 42).map((line) => {
                const trimmed = line.trim();
                if (trimmed.startsWith('# ')) return 'h1';
                if (trimmed.startsWith('## ')) return 'h2';
                if (trimmed.startsWith('### ')) return 'h3';
                if (trimmed.startsWith('```')) return 'code';
                if (/^>\s*(\[!TIP]|Tip:)/i.test(trimmed)) return 'tip';
                if (/^(- |\* |\d+\. )/.test(trimmed)) return 'list';
                if (trimmed.startsWith('> ')) return 'quote';
                if (/^(---|\*\*\*)$/.test(trimmed)) return 'hr';
                if (!trimmed) return 'empty';
                return 'text';
            });
            this.previewHtml = renderMarkdownPreview(text);
            this.parseHeadings();
        },

        parseHeadings() {
            this.headings = (this.content || '').split('\n').flatMap((line, index) => {
                const match = line.match(/^(#{1,3})\s+(.+)$/);

                return match ? [{ level: match[1].length, title: match[2].trim(), line: index + 1 }] : [];
            });

            if (this.headings.length > 0 && !this.activeHeading) {
                this.activeHeading = this.headings[0].title;
            }
        },

        insertFormat(prefix, suffix = '', defaultText = '') {
            if (!this.canEdit) return;
            if (this.editorInstance) {
                if (prefix === '**' && suffix === '**') return this.editorInstance.bold();
                if (prefix === '*' && suffix === '*') return this.editorInstance.italic();
                if (prefix === '~~' && suffix === '~~') return this.editorInstance.strikethrough();
                if (prefix === '`' && suffix === '`') return this.editorInstance.inlineCode();
                if (prefix === '[[' && suffix === ']]') return this.editorInstance.wikilink();
                if (prefix === '[' && suffix.startsWith('](')) return this.editorInstance.link();
                if (prefix === '![' && suffix.startsWith('](')) return this.editorInstance.image();
                if (prefix.startsWith('> [!')) return this.editorInstance.callout('TIP');
                if (prefix.includes('---')) return this.editorInstance.horizontalRule();
                if (prefix.startsWith('```')) return this.editorInstance.codeBlock('javascript');
            }
            const textarea = this.$refs.editorTextarea;
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end) || defaultText;
            textarea.setRangeText(prefix + selected + suffix, start, end, 'select');
            this.content = textarea.value;
            textarea.focus();
        },

        insertLinePrefix(prefix) {
            if (!this.canEdit) return;
            if (this.editorInstance) {
                if (prefix.startsWith('# ')) return this.editorInstance.heading(1);
                if (prefix.startsWith('## ')) return this.editorInstance.heading(2);
                if (prefix.startsWith('### ')) return this.editorInstance.heading(3);
                if (prefix.startsWith('- [ ] ')) return this.editorInstance.taskList();
                if (prefix.startsWith('- ')) return this.editorInstance.bulletList();
                if (prefix.startsWith('1. ')) return this.editorInstance.numberedList();
            }
            const textarea = this.$refs.editorTextarea;
            if (!textarea) return;
            const start = textarea.selectionStart;
            const lineStart = textarea.value.lastIndexOf('\n', start - 1) + 1;
            textarea.setRangeText(prefix, lineStart, lineStart, 'end');
            this.content = textarea.value;
            textarea.focus();
        },

        insertTable() {
            if (!this.canEdit) return;
            if (this.editorInstance) {
                return this.editorInstance.table();
            }
            this.insertFormat('', '', '\n| Header 1 | Header 2 | Header 3 |\n| --- | --- | --- |\n| Cell 1 | Cell 2 | Cell 3 |\n| Cell 4 | Cell 5 | Cell 6 |\n\n');
        },

        scrollToHeading(heading) {
            this.activeHeading = heading.title;
            if (this.editorInstance) {
                this.editorInstance.scrollToHeading(heading);
            }
            const preview = this.$refs.previewPane;
            if (!preview) return;
            const element = Array.from(preview.querySelectorAll('h1, h2, h3'))
                .find((candidate) => candidate.textContent.includes(heading.title));
            element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        handleEditorKeydown(event) {
            const modified = event.metaKey || event.ctrlKey;

            if (modified && event.key.toLowerCase() === 's') {
                event.preventDefault();
                this.saveEditor();
                return;
            }

            if (modified && event.key.toLowerCase() === 'b') {
                event.preventDefault();
                this.insertFormat('**', '**', 'bold text');
                return;
            }

            if (modified && event.key.toLowerCase() === 'i') {
                event.preventDefault();
                this.insertFormat('*', '*', 'italic text');
                return;
            }

            if (event.key === 'Tab') {
                event.preventDefault();
                this.insertFormat('  ');
                return;
            }

            const pairs = { '(': ')', '[': ']', '{': '}', '`': '`', '"': '"', "'": "'" };
            if (!this.canEdit || !pairs[event.key]) return;
            const textarea = this.$refs.editorTextarea;
            if (!textarea || textarea.selectionStart === textarea.selectionEnd) return;
            event.preventDefault();
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end);
            textarea.setRangeText(event.key + selected + pairs[event.key], start, end, 'select');
            this.content = textarea.value;
        },

        handleWindowKeydown(event) {
            if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') return;
            event.preventDefault();
            this.sidebarOpen = true;
            this.$nextTick(() => this.$refs.editorSearch?.focus());
        },

        async saveEditor() {
            if (!this.canEdit || !this.isDirty || this.isSaving || !this.$wire) return false;
            this.isSaving = true;
            this.saveFailed = false;
            const savedContent = this.editorInstance ? this.editorInstance.getContent() : this.content;
            this.content = savedContent;

            try {
                const vc = getVaultCrypto();
                if (this.isEncrypted && vc) {
                    const key = vc.getSessionKey?.(this.vaultSlug);
                    if (!key) {
                        this.isUnlocked = false;
                        this.unlockError = 'Session key missing. Please unlock with passphrase before saving.';
                        this.isSaving = false;
                        return false;
                    }

                    const encrypted = await vc.encryptText(savedContent, key);
                    const plaintextSize = typeof TextEncoder !== 'undefined'
                        ? new TextEncoder().encode(savedContent).length
                        : savedContent.length;
                    await this.$wire.saveEncryptedFile(encrypted.ciphertextBase64, encrypted.ivHex, encrypted.tagHex, plaintextSize);
                    this.encryptionIv = encrypted.ivHex;
                    this.encryptionTag = encrypted.tagHex;
                } else {
                    this.$wire.editorContent = savedContent;
                    await this.$wire.saveFile();
                }

                this.initialContent = savedContent;
                this.isDirty = false;
                this.$wire.editorIsDirty = false;

                return true;
            } catch (err) {
                console.error('Failed to save note:', err);
                this.saveFailed = true;

                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async copyShareLink() {
            if (!window.SynkkClipboard?.copy) {
                this.shareStatus = 'failed';
                return;
            }

            const result = await window.SynkkClipboard.copy(window.location.href);
            this.shareStatus = result.copied ? 'copied' : 'failed';
            window.setTimeout(() => { this.shareStatus = 'idle'; }, 2500);
        },

        async openFile(fileId) {
            if (!this.$wire || (this.isDirty && !window.confirm(options.unsavedPrompt || 'Discard unsaved changes?'))) return;
            this.cleanupCollaboration();
            await this.$wire.selectFile(fileId);
        },

        async closeEditor() {
            if (!this.$wire || (this.isDirty && !window.confirm(options.unsavedPrompt || 'Discard unsaved changes?'))) return;
            this.cleanupCollaboration();
            await this.$wire.$set('activeTab', 'files');
        },
    };
}

if (typeof window !== 'undefined') {
    window.markdownEditor = createMarkdownEditor;
}
