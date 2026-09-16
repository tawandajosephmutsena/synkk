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

test('opens a graph node in a portal using selectNote', async () => {
    const graph = createVaultGraph({
        nodes: [{ id: 18, name: 'Guide.md', path: 'docs/Guide.md' }],
    });
    let selectedPath = null;
    let switchedLayout = null;

    graph.$wire = {
        graphModalOpen: true,
        async selectNote(path) {
            selectedPath = path;
        },
        async switchLayout(layout) {
            switchedLayout = layout;
        },
    };

    assert.equal(await graph.selectNode(18), true);
    assert.equal(selectedPath, 'docs/Guide.md');
    assert.equal(switchedLayout, 'docs');
    assert.equal(graph.$wire.graphModalOpen, false);
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

test('supports emerald and amber themes', () => {
    const emeraldGraph = createVaultGraph({ theme: 'emerald' });
    const amberGraph = createVaultGraph({ theme: 'amber' });

    assert.equal(emeraldGraph.theme.accent, '#10b981');
    assert.equal(amberGraph.theme.accent, '#f59e0b');
});

test('fitToViewport calculates framing zoom and centers pan correctly', () => {
    const graph = createVaultGraph({
        nodes: [
            { id: 1, name: 'A', x: -200, y: -200, radius: 10 },
            { id: 2, name: 'B', x: 200, y: 200, radius: 10 },
        ],
    });

    graph.viewportWidth = 800;
    graph.viewportHeight = 600;

    graph.fitToViewport(0.8);

    assert.ok(graph.zoom > 0 && graph.zoom <= 1.25);
    // Pan should place center (0,0) near viewport center (400, 300)
    assert.equal(graph.panX, 400);
    assert.equal(graph.panY, 300);
});

test('simulation applies collision separation force when nodes are too close', () => {
    const graph = createVaultGraph({
        nodes: [
            { id: 1, name: 'A', x: 0, y: 0, radius: 10 },
            { id: 2, name: 'B', x: 5, y: 0, radius: 10 }, // distance 5 < minSpacing (10+10+16 = 36)
        ],
    });

    const mockCanvas = {
        getContext() {
            return {
                setTransform() {},
                clearRect() {},
                save() {},
                restore() {},
                translate() {},
                scale() {},
                beginPath() {},
                moveTo() {},
                lineTo() {},
                arc() {},
                fill() {},
                stroke() {},
                strokeText() {},
                fillText() {},
            };
        },
        getBoundingClientRect() {
            return { width: 600, height: 600, left: 0, top: 0 };
        },
        style: {},
        addEventListener() {},
        removeEventListener() {},
    };

    graph.$refs = { graphCanvas: mockCanvas };
    graph.init();

    // Reset positions manually to test the collision step directly
    graph.nodes[0].x = 0;
    graph.nodes[0].y = 0;
    graph.nodes[0].vx = 0;
    graph.nodes[0].vy = 0;

    graph.nodes[1].x = 5;
    graph.nodes[1].y = 0;
    graph.nodes[1].vx = 0;
    graph.nodes[1].vy = 0;

    graph.simulationStepHandler();

    // Node 0 should be pushed left (vx < 0) and Node 1 pushed right (vx > 0)
    assert.ok(graph.nodes[0].vx < 0, `Expected node 0 to push left, got ${graph.nodes[0].vx}`);
    assert.ok(graph.nodes[1].vx > 0, `Expected node 1 to push right, got ${graph.nodes[1].vx}`);

    graph.destroy();
});

