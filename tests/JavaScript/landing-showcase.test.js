import test from 'node:test';
import assert from 'node:assert/strict';

import { productViewForKey } from '../../resources/js/landing-showcase.js';

test('moves between product views with arrow, home, and end keys', () => {
    assert.equal(productViewForKey('markdown-editor', 'ArrowRight'), 'graph');
    assert.equal(productViewForKey('graph', 'ArrowRight'), 'markdown-editor');
    assert.equal(productViewForKey('graph', 'ArrowLeft'), 'markdown-editor');
    assert.equal(productViewForKey('markdown-editor', 'End'), 'graph');
    assert.equal(productViewForKey('graph', 'Home'), 'markdown-editor');
    assert.equal(productViewForKey('graph', 'Enter'), 'graph');
});
