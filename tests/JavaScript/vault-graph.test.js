import assert from 'node:assert/strict';
import test from 'node:test';

import { createVaultGraph } from '../../resources/js/vault-graph.js';

test('initializes graph nodes and edges properly', () => {
    const data = {
        nodes: [
            { id: 1, name: 'Index.md', path: 'Index.md', linksCount: 3, version: 1 },
            { id: 2, name: 'Guide.md', path: 'docs/Guide.md', linksCount: 1, version: 2 },
        ],
        edges: [
            { source: 0, target: 1 },
        ],
    };

    const graph = createVaultGraph(data);

    assert.equal(graph.nodes.length, 2);
    assert.equal(graph.edges.length, 1);
    assert.equal(graph.zoom, 1);
    assert.equal(graph.nodes[0].radius, 4 + Math.min(3 * 2, 10));
    assert.equal(graph.nodes[1].radius, 4 + Math.min(1 * 2, 10));
});

test('handles zoom in, zoom out, and resetView', () => {
    const graph = createVaultGraph();

    graph.zoomIn();
    assert.ok(graph.zoom > 1);

    graph.zoomOut();
    assert.ok(graph.zoom < 1.2);

    graph.search = 'query';
    graph.resetView();
    assert.equal(graph.zoom, 1);
    assert.equal(graph.search, '');
});

test('opens a graph node in the editor through one server action', async () => {
    const graph = createVaultGraph({
        nodes: [{ id: 17, name: 'Launch.md', path: 'Launch.md' }],
    });
    let openedFileId = null;

    graph.$wire = {
        async openFileInEditor(fileId) {
            openedFileId = fileId;
        },
    };

    assert.equal(await graph.selectNode(17), true);
    assert.equal(openedFileId, 17);
});

test('cleans up animation and listeners on destroy', () => {
    const graph = createVaultGraph();
    let cancelledId = null;

    globalThis.window = {
        cancelAnimationFrame(id) {
            cancelledId = id;
        },
        removeEventListener(event, handler) {},
    };

    graph.animId = 42;
    graph.resizeHandler = () => {};

    graph.destroy();

    assert.equal(cancelledId, 42);
    assert.equal(graph.animId, null);
    assert.equal(graph.resizeHandler, null);

    delete globalThis.window;
});
