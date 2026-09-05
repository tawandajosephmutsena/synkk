import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeMobileMenu, initializeSurfaceShowcase } from '../../resources/js/landing-controls.js';

class Control extends EventTarget {
    attributes = new Map();
    label = { textContent: '' };
    dataset = {};
    hidden = true;

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }
    querySelector() {
        return this.label;
    }
}

const setupSurfaces = (active = 'overview') => {
    const showcase = new Control();
    const keys = ['overview', 'write', 'connect', 'control'];
    const tabs = keys.map((key) => {
        const tab = new Control();
        tab.dataset.surfaceTab = key;
        tab.focus = () => {
            showcase.focused = key;
        };
        return tab;
    });
    const panels = keys.flatMap((key) => [
        Object.assign(new Control(), { dataset: { surfaceCopy: key } }),
        Object.assign(new Control(), { dataset: { surfaceImage: key } }),
    ]);
    showcase.dataset.activeSurface = active;
    showcase.querySelectorAll = (selector) => (selector === '[data-surface-tab]' ? tabs : panels);
    const cleanup = initializeSurfaceShowcase(showcase);

    return { showcase, tabs, panels, cleanup };
};

const selectedPanels = (panels) => panels.filter((panel) => !panel.hidden).map((panel) => panel.dataset);
const press = (tab, key) => {
    const event = new Event('keydown', { cancelable: true });
    event.key = key;
    tab.dispatchEvent(event);
    return event;
};

test('opens the initial surface with its matching copy and image', () => {
    const { tabs, panels } = setupSurfaces();

    assert.equal(tabs[0].attributes.get('aria-selected'), 'true');
    assert.equal(tabs[0].tabIndex, 0);
    assert.equal(tabs[1].tabIndex, -1);
    assert.deepEqual(selectedPanels(panels), [{ surfaceCopy: 'overview' }, { surfaceImage: 'overview' }]);
});

test('switches the description and image together when a surface is selected', () => {
    const { showcase, tabs, panels } = setupSurfaces();

    tabs[3].dispatchEvent(new Event('click'));

    assert.equal(showcase.dataset.activeSurface, 'control');
    assert.equal(tabs[0].attributes.get('aria-selected'), 'false');
    assert.equal(tabs[3].attributes.get('aria-selected'), 'true');
    assert.deepEqual(selectedPanels(panels), [{ surfaceCopy: 'control' }, { surfaceImage: 'control' }]);
});

test('moves focus through all four surfaces and wraps with arrow, Home, and End keys', () => {
    const { showcase, tabs, panels } = setupSurfaces();

    assert.equal(press(tabs[0], 'ArrowLeft').defaultPrevented, true);
    assert.equal(showcase.focused, 'control');
    press(tabs[3], 'ArrowRight');
    assert.equal(showcase.focused, 'overview');
    press(tabs[0], 'End');
    assert.equal(showcase.focused, 'control');
    press(tabs[3], 'Home');
    assert.equal(showcase.focused, 'overview');
    press(tabs[0], 'ArrowDown');
    assert.equal(showcase.focused, 'write');
    assert.deepEqual(selectedPanels(panels), [{ surfaceCopy: 'write' }, { surfaceImage: 'write' }]);
    assert.equal(press(tabs[1], 'Tab').defaultPrevented, false);
    assert.equal(showcase.dataset.activeSurface, 'write');
});

test('falls back to the first surface for a stale initial selection and keeps viewers independent', () => {
    const first = setupSurfaces('unknown');
    const second = setupSurfaces('connect');

    first.tabs[1].dispatchEvent(new Event('click'));

    assert.equal(first.showcase.dataset.activeSurface, 'write');
    assert.equal(second.showcase.dataset.activeSurface, 'connect');
    assert.deepEqual(selectedPanels(second.panels), [{ surfaceCopy: 'connect' }, { surfaceImage: 'connect' }]);
});

test('removes the surface interaction handlers on cleanup', () => {
    const { showcase, tabs, cleanup } = setupSurfaces();

    cleanup();
    tabs[2].dispatchEvent(new Event('click'));
    press(tabs[0], 'End');

    assert.equal(showcase.dataset.activeSurface, 'overview');
});

test('closes mobile navigation with Escape and returns focus to its disclosure', () => {
    const menu = new Control();
    let focused = false;
    menu.open = true;
    menu.label.focus = () => {
        focused = true;
    };
    initializeMobileMenu(menu);
    const event = new Event('keydown');
    event.key = 'Escape';

    menu.dispatchEvent(event);

    assert.equal(menu.open, false);
    assert.equal(focused, true);
});

test('closes mobile navigation only when a navigation link is activated', () => {
    const menu = new Control();
    menu.open = true;
    menu.closest = () => null;
    initializeMobileMenu(menu);
    menu.dispatchEvent(new Event('click'));
    assert.equal(menu.open, true);

    menu.closest = () => ({ tagName: 'A' });
    menu.dispatchEvent(new Event('click'));
    assert.equal(menu.open, false);
});
