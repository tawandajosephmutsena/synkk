const productViews = ['markdown-editor', 'graph'];

export const productViewForKey = (currentView, key) => {
    const currentIndex = Math.max(productViews.indexOf(currentView), 0);

    if (key === 'Home') {
        return productViews[0];
    }

    if (key === 'End') {
        return productViews.at(-1);
    }

    if (key === 'ArrowRight' || key === 'ArrowDown') {
        return productViews[(currentIndex + 1) % productViews.length];
    }

    if (key === 'ArrowLeft' || key === 'ArrowUp') {
        return productViews[(currentIndex - 1 + productViews.length) % productViews.length];
    }

    return currentView;
};

export const initializeProductWorkbench = (workbench) => {
    const tabs = [...workbench.querySelectorAll('[data-product-tab]')];
    const panels = [...workbench.querySelectorAll('[data-product-view]')];

    const activateView = (view, shouldFocus = false) => {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.productTab === view;

            tab.setAttribute('aria-selected', String(isActive));
            tab.tabIndex = isActive ? 0 : -1;

            if (isActive && shouldFocus) {
                tab.focus();
            }
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.productView !== view;
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activateView(tab.dataset.productTab));
        tab.addEventListener('keydown', (event) => {
            const nextView = productViewForKey(tab.dataset.productTab, event.key);

            if (nextView === tab.dataset.productTab) {
                return;
            }

            event.preventDefault();
            activateView(nextView, true);
        });
    });
};

if (typeof document !== 'undefined') {
    const initialize = () => {
        document.querySelectorAll('[data-product-workbench]').forEach(initializeProductWorkbench);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}
