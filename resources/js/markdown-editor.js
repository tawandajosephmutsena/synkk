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

const renderInlineMarkdown = (value = '') => {
    const tokens = [];
    const storeToken = (html) => {
        const key = `\u0000SYNKK${tokens.length}\u0000`;
        tokens.push(html);

        return key;
    };

    let output = String(value);

    output = output.replace(/`([^`]+)`/g, (_, code) => storeToken(`<code>${escapeHtml(code)}</code>`));
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

        const fence = trimmed.match(/^```([^\s]*)/);
        if (fence) {
            const code = [];
            index += 1;

            while (index < lines.length && !lines[index].trim().startsWith('```')) {
                code.push(lines[index]);
                index += 1;
            }

            if (index < lines.length) {
                index += 1;
            }

            const language = fence[1] ? ` data-language="${escapeHtml(fence[1])}"` : '';
            html.push(`<pre${language}><code>${escapeHtml(code.join('\n'))}</code></pre>`);
            continue;
        }

        const heading = line.match(/^(#{1,6})\s+(.+)$/);
        if (heading) {
            const level = heading[1].length;
            html.push(`<h${level}>${renderInlineMarkdown(heading[2])}</h${level}>`);
            index += 1;
            continue;
        }

        const callout = line.match(/^>\s*\[!(TIP|NOTE|WARNING|IMPORTANT|CAUTION)]\s*(.*)$/i);
        if (callout) {
            const body = [];
            const type = callout[1].toLowerCase();
            const title = callout[2].trim() || callout[1].toLowerCase().replace(/^./, (letter) => letter.toUpperCase());
            index += 1;

            while (index < lines.length && /^>\s?/.test(lines[index])) {
                body.push(lines[index].replace(/^>\s?/, ''));
                index += 1;
            }

            html.push(`<aside class="synkk-callout synkk-callout--${type}"><strong>${escapeHtml(title)}</strong><p>${renderInlineMarkdown(body.join(' '))}</p></aside>`);
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

        init() {
            if (typeof window !== 'undefined' && window.innerWidth < 1024 && this.viewMode === 'split') {
                this.viewMode = 'source';
            }

            this.updateMetrics();
            this.$watch('content', (value) => {
                this.updateMetrics();
                this.isDirty = this.initialContent === null || value !== this.initialContent;

                if (this.$wire) {
                    this.$wire.editorContent = value;
                    this.$wire.editorIsDirty = this.isDirty;
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

        destroy() {
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
            const textarea = this.$refs.editorTextarea;
            if (!textarea) return;
            const start = textarea.selectionStart;
            const lineStart = textarea.value.lastIndexOf('\n', start - 1) + 1;
            textarea.setRangeText(prefix, lineStart, lineStart, 'end');
            this.content = textarea.value;
            textarea.focus();
        },

        insertTable() {
            this.insertFormat('', '', '\n| Header 1 | Header 2 | Header 3 |\n| --- | --- | --- |\n| Cell 1 | Cell 2 | Cell 3 |\n| Cell 4 | Cell 5 | Cell 6 |\n\n');
        },

        scrollToHeading(heading) {
            this.activeHeading = heading.title;
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
            const savedContent = this.content;

            try {
                this.$wire.editorContent = savedContent;
                await this.$wire.saveFile();
                this.initialContent = savedContent;
                this.isDirty = false;
                this.$wire.editorIsDirty = false;

                return true;
            } catch {
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
            await this.$wire.selectFile(fileId);
        },

        async closeEditor() {
            if (!this.$wire || (this.isDirty && !window.confirm(options.unsavedPrompt || 'Discard unsaved changes?'))) return;
            await this.$wire.$set('activeTab', 'files');
        },
    };
}

if (typeof window !== 'undefined') {
    window.markdownEditor = createMarkdownEditor;
}
