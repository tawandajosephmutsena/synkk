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
