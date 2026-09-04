function copyWithTemporaryField(text) {
    if (typeof document === 'undefined' || typeof document.execCommand !== 'function') {
        return false;
    }

    const previousFocus = document.activeElement;
    const field = document.createElement('textarea');

    field.value = text;
    field.setAttribute('readonly', '');
    field.setAttribute('aria-hidden', 'true');
    field.style.position = 'fixed';
    field.style.inset = '0 auto auto -9999px';
    field.style.opacity = '0';

    document.body.append(field);
    field.focus();
    field.select();

    let copied = false;

    try {
        copied = document.execCommand('copy') === true;
    } catch {
        copied = false;
    } finally {
        field.remove();
        previousFocus?.focus?.();
    }

    return copied;
}

export async function copyTextToClipboard(text, options = {}) {
    const clipboard = Object.hasOwn(options, 'clipboard')
        ? options.clipboard
        : globalThis.navigator?.clipboard;
    const fallbackCopy = options.fallbackCopy ?? copyWithTemporaryField;

    if (clipboard?.writeText) {
        try {
            await clipboard.writeText(text);

            return { copied: true, method: 'clipboard' };
        } catch {
            // A rejected Clipboard API write should still get the synchronous fallback.
        }
    }

    try {
        if (fallbackCopy(text) === true) {
            return { copied: true, method: 'fallback' };
        }
    } catch {
        // The caller will present a manual keyboard-copy path.
    }

    return { copied: false, method: null };
}

if (typeof window !== 'undefined') {
    window.SynkkClipboard = {
        copy: copyTextToClipboard,
    };
}
