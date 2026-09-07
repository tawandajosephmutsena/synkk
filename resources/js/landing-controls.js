export const initializeSurfaceShowcase = (showcase) => {
    if (!showcase) {
        return () => {};
    }

    const tabs = [...showcase.querySelectorAll('[data-surface-tab]')];
    const panels = [...showcase.querySelectorAll('[data-surface-copy], [data-surface-image]')];
    const views = tabs.map((tab) => tab.dataset.surfaceTab);
    const listeners = [];

    const activate = (view, focus = false) => {
        if (!views.includes(view)) {
            return;
        }

        showcase.dataset.activeSurface = view;
        tabs.forEach((tab) => {
            const active = tab.dataset.surfaceTab === view;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;

            if (active && focus) {
                tab.focus();
            }
        });
        panels.forEach((panel) => {
            panel.hidden = (panel.dataset.surfaceCopy ?? panel.dataset.surfaceImage) !== view;
        });
    };

    tabs.forEach((tab, index) => {
        const click = () => activate(tab.dataset.surfaceTab);
        const keydown = (event) => {
            let nextIndex;

            if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = views.length - 1;
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (index + 1) % views.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (index - 1 + views.length) % views.length;
            } else {
                return;
            }

            event.preventDefault();
            activate(views[nextIndex], true);
        };

        tab.addEventListener('click', click);
        tab.addEventListener('keydown', keydown);
        listeners.push(() => {
            tab.removeEventListener('click', click);
            tab.removeEventListener('keydown', keydown);
        });
    });

    activate(views.includes(showcase.dataset.activeSurface) ? showcase.dataset.activeSurface : views[0]);

    return () => listeners.forEach((remove) => remove());
};

export const initializeMobileMenu = (menu) => {
    const summary = menu.querySelector('summary');
    const closeAfterLink = (event) => {
        if (event.target.closest('a')) {
            menu.open = false;
        }
    };
    const closeOnEscape = (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            summary.focus();
        }
    };

    menu.addEventListener('click', closeAfterLink);
    menu.addEventListener('keydown', closeOnEscape);

    return () => {
        menu.removeEventListener('click', closeAfterLink);
        menu.removeEventListener('keydown', closeOnEscape);
    };
};

export const initializeThemeSwitcher = () => {
    const STORAGE_KEY = 'synkk-theme';

    const getStoredTheme = () => localStorage.getItem(STORAGE_KEY) || 'system';

    const applyTheme = (theme) => {
        const isDark =
            theme === 'dark' ||
            (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isDark) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
        }

        document.querySelectorAll('[data-theme-set]').forEach((btn) => {
            const btnTheme = btn.getAttribute('data-theme-set');
            const isActive = btnTheme === theme;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
    };

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-theme-set]');
        if (btn) {
            const theme = btn.getAttribute('data-theme-set');
            localStorage.setItem(STORAGE_KEY, theme);
            applyTheme(theme);
        }
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });

    applyTheme(getStoredTheme());
};

