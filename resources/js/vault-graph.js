const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
const DEFAULT_SIMULATION_TICKS = 240;
const INTERACTION_SIMULATION_TICKS = 90;
const STABLE_SPEED_THRESHOLD = 0.035;
const STABLE_FRAME_LIMIT = 20;

export const THEMES = {
    emerald: {
        accent: '#10b981',
        accentGlow: 'rgba(16, 185, 129, 0.45)',
        accentHighlight: '#34d399',
        accentText: '#6ee7b7',
        hubColor: '#059669',
        hubBorder: '#10b981',
        nodeBase: 'rgba(148, 163, 184, 0.75)',
        nodeDimmed: 'rgba(71, 85, 105, 0.22)',
        edgeNormal: 'rgba(255, 255, 255, 0.12)',
        edgeConnected: 'rgba(16, 185, 129, 0.85)',
        edgeDimmed: 'rgba(255, 255, 255, 0.03)',
    },
    amber: {
        accent: '#f59e0b',
        accentGlow: 'rgba(245, 158, 11, 0.45)',
        accentHighlight: '#fbbf24',
        accentText: '#fde68a',
        hubColor: '#d97706',
        hubBorder: '#f59e0b',
        nodeBase: 'rgba(148, 163, 184, 0.75)',
        nodeDimmed: 'rgba(71, 85, 105, 0.22)',
        edgeNormal: 'rgba(255, 255, 255, 0.12)',
        edgeConnected: 'rgba(245, 158, 11, 0.85)',
        edgeDimmed: 'rgba(255, 255, 255, 0.03)',
    },
};

export function createVaultGraph(data = {}) {
    const themeName = data.theme === 'amber' ? 'amber' : 'emerald';
    const activeTheme = {
        ...THEMES[themeName],
        ...(data.accentColor ? { accent: data.accentColor, accentHighlight: data.accentColor } : {}),
    };

    return {
        nodes: (data.nodes || []).map((node) => ({
            ...node,
            x: node.x ?? 0,
            y: node.y ?? 0,
            vx: 0,
            vy: 0,
            radius: 4 + Math.min((node.linksCount || 0) * 2, 10),
        })),
        edges: data.edges || [],
        theme: activeTheme,
        search: '',
        hoveredNode: null,
        tooltipX: 0,
        tooltipY: 0,
        zoom: 1,
        panX: 0,
        panY: 0,
        viewportWidth: 0,
        viewportHeight: 0,
        pixelRatio: 1,
        isDragging: false,
        dragNode: null,
        pointerDownNode: null,
        activePointerId: null,
        pointerStartX: 0,
        pointerStartY: 0,
        dragOriginX: 0,
        dragOriginY: 0,
        lastMouseX: 0,
        lastMouseY: 0,
        hasDragged: false,
        dragThreshold: 5,
        userHasAdjustedView: false,
        hasInitialFitted: false,
        animId: null,
        resizeHandler: null,
        resizeObserver: null,
        eventHandlers: null,
        canvasCleanupHandler: null,
        motionQuery: null,
        motionChangeHandler: null,
        searchWatcherCleanup: null,
        requestRenderHandler: null,
        drawHandler: null,
        frameHandler: null,
        simulationStepHandler: null,
        simulationActive: false,
        simulationTicks: 0,
        simulationTickLimit: DEFAULT_SIMULATION_TICKS,
        stableFrameCount: 0,
        prefersReducedMotion: false,
        hasCenteredView: false,
        destroyed: true,
        previousTouchAction: null,

        init() {
            const canvas = this.$refs?.graphCanvas;
            if (!canvas || typeof canvas.getContext !== 'function') {
                return;
            }

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return;
            }

            this.destroyed = false;
            this.previousTouchAction = canvas.style?.touchAction ?? null;
            if (canvas.style) {
                canvas.style.touchAction = 'none';
            }

            const browserWindow = typeof window !== 'undefined' ? window : null;
            this.motionQuery = browserWindow?.matchMedia?.('(prefers-reduced-motion: reduce)') ?? null;
            this.prefersReducedMotion = this.motionQuery?.matches === true;

            const requestFrame = browserWindow?.requestAnimationFrame?.bind(browserWindow);
            const cancelFrame = browserWindow?.cancelAnimationFrame?.bind(browserWindow);

            this.drawHandler = () => {
                if (this.destroyed) {
                    return;
                }

                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.setTransform(this.pixelRatio, 0, 0, this.pixelRatio, 0, 0);
                ctx.save();
                ctx.translate(this.panX, this.panY);
                ctx.scale(this.zoom, this.zoom);

                const scaleFactor = Math.pow(this.zoom, 0.45);
                const theme = this.theme;

                // 1. Hovered node & connected neighbors identification
                const hoveredNode = this.hoveredNode;
                const connectedNodeSet = new Set();
                const connectedEdgeSet = new Set();

                if (hoveredNode) {
                    connectedNodeSet.add(hoveredNode);
                    for (let edgeIndex = 0; edgeIndex < this.edges.length; edgeIndex++) {
                        const edge = this.edges[edgeIndex];
                        const source = this.nodes[edge.source];
                        const target = this.nodes[edge.target];
                        if (source === hoveredNode && target) {
                            connectedNodeSet.add(target);
                            connectedEdgeSet.add(edgeIndex);
                        } else if (target === hoveredNode && source) {
                            connectedNodeSet.add(source);
                            connectedEdgeSet.add(edgeIndex);
                        }
                    }
                }

                const hasSearch = Boolean(this.search && this.search.trim().length > 0);
                const searchLower = hasSearch ? this.search.trim().toLowerCase() : '';

                // 2. Draw Edges
                for (let edgeIndex = 0; edgeIndex < this.edges.length; edgeIndex++) {
                    const edge = this.edges[edgeIndex];
                    const source = this.nodes[edge.source];
                    const target = this.nodes[edge.target];
                    if (!source || !target) {
                        continue;
                    }

                    const isConnectedToHover = connectedEdgeSet.has(edgeIndex);

                    ctx.beginPath();
                    ctx.moveTo(source.x, source.y);
                    ctx.lineTo(target.x, target.y);

                    if (hoveredNode) {
                        if (isConnectedToHover) {
                            ctx.strokeStyle = theme.edgeConnected;
                            ctx.lineWidth = 2.2 / Math.pow(this.zoom, 0.4);
                        } else {
                            ctx.strokeStyle = theme.edgeDimmed;
                            ctx.lineWidth = 0.8 / Math.pow(this.zoom, 0.4);
                        }
                    } else if (hasSearch) {
                        const sourceMatches = source.name && source.name.toLowerCase().includes(searchLower);
                        const targetMatches = target.name && target.name.toLowerCase().includes(searchLower);
                        if (sourceMatches || targetMatches) {
                            ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
                            ctx.lineWidth = 1.2 / Math.pow(this.zoom, 0.4);
                        } else {
                            ctx.strokeStyle = theme.edgeDimmed;
                            ctx.lineWidth = 0.7 / Math.pow(this.zoom, 0.4);
                        }
                    } else {
                        ctx.strokeStyle = theme.edgeNormal;
                        ctx.lineWidth = 1 / Math.pow(this.zoom, 0.4);
                    }

                    ctx.stroke();
                }

                // 3. Draw Nodes (Nodes styled with authentic Obsidian depth)
                for (const node of this.nodes) {
                    const isHovered = hoveredNode === node;
                    const isNeighbor = connectedNodeSet.has(node) && !isHovered;
                    const isMatch = !hasSearch || (node.name && node.name.toLowerCase().includes(searchLower));
                    const isHub = (node.linksCount || 0) >= 4;

                    const visualRadius = (node.radius * (isHovered ? 1.4 : isNeighbor ? 1.15 : 1)) / scaleFactor;

                    ctx.beginPath();
                    ctx.arc(node.x, node.y, visualRadius, 0, Math.PI * 2);

                    if (isHovered) {
                        ctx.fillStyle = theme.accentHighlight;
                        ctx.shadowColor = theme.accent;
                        ctx.shadowBlur = 14 / scaleFactor;
                        ctx.fill();
                        ctx.lineWidth = 2 / scaleFactor;
                        ctx.strokeStyle = '#ffffff';
                        ctx.stroke();
                        ctx.shadowBlur = 0;
                    } else if (hoveredNode) {
                        if (isNeighbor) {
                            ctx.fillStyle = theme.accent;
                            ctx.shadowBlur = 0;
                            ctx.fill();
                            ctx.lineWidth = 1.5 / scaleFactor;
                            ctx.strokeStyle = theme.accentHighlight;
                            ctx.stroke();
                        } else {
                            ctx.fillStyle = theme.nodeDimmed;
                            ctx.shadowBlur = 0;
                            ctx.fill();
                        }
                    } else if (hasSearch) {
                        if (isMatch) {
                            ctx.fillStyle = theme.accent;
                            ctx.shadowBlur = 8 / scaleFactor;
                            ctx.shadowColor = theme.accent;
                            ctx.fill();
                            ctx.shadowBlur = 0;
                        } else {
                            ctx.fillStyle = theme.nodeDimmed;
                            ctx.shadowBlur = 0;
                            ctx.fill();
                        }
                    } else {
                        ctx.shadowBlur = 0;
                        if (isHub) {
                            ctx.fillStyle = theme.hubColor;
                            ctx.fill();
                            ctx.lineWidth = 1.2 / scaleFactor;
                            ctx.strokeStyle = theme.hubBorder;
                            ctx.stroke();
                        } else {
                            ctx.fillStyle = theme.nodeBase;
                            ctx.fill();
                        }
                    }

                    // 4. Selective, Crisp Label Rendering (Prevents Overlapping Text Clutter)
                    const shouldDrawLabel = isHovered
                        || isNeighbor
                        || (hasSearch && isMatch)
                        || (this.zoom >= 1.3 && isHub)
                        || (this.zoom >= 2.0);

                    if (shouldDrawLabel) {
                        const fontSize = Math.max(8, Math.min(13, (isHovered ? 12 : 9.5) / Math.pow(this.zoom, 0.45)));
                        ctx.font = isHovered ? `bold ${fontSize}px sans-serif` : `${fontSize}px sans-serif`;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'top';

                        const labelY = node.y + visualRadius + (4 / scaleFactor);
                        const labelText = node.name || '';
                        const displayLabel = labelText.length > 28 && !isHovered && !hasSearch
                            ? labelText.slice(0, 26) + '…'
                            : labelText;

                        // Dark outline halo so text is always crisp and readable across links
                        ctx.lineJoin = 'round';
                        ctx.lineWidth = 3.5 / scaleFactor;
                        ctx.strokeStyle = 'rgba(12, 15, 18, 0.92)';
                        ctx.strokeText(displayLabel, node.x, labelY);

                        if (isHovered) {
                            ctx.fillStyle = '#ffffff';
                        } else if (isNeighbor || (hasSearch && isMatch)) {
                            ctx.fillStyle = theme.accentText;
                        } else {
                            ctx.fillStyle = 'rgba(226, 232, 240, 0.85)';
                        }
                        ctx.fillText(displayLabel, node.x, labelY);
                    }
                }

                ctx.restore();
            };

            this.simulationStepHandler = () => {
                const nodeCount = Math.max(1, this.nodes.length);
                const repulsion = 2400;
                const maxRepulsionDistance = Math.max(300, Math.min(600, Math.sqrt(nodeCount) * 35));

                // 1. Pairwise repulsion & hard collision separation
                for (let firstIndex = 0; firstIndex < this.nodes.length; firstIndex++) {
                    for (let secondIndex = firstIndex + 1; secondIndex < this.nodes.length; secondIndex++) {
                        const firstNode = this.nodes[firstIndex];
                        const secondNode = this.nodes[secondIndex];
                        const deltaX = secondNode.x - firstNode.x;
                        const deltaY = secondNode.y - firstNode.y;
                        const distance = Math.hypot(deltaX, deltaY) || 1;

                        if (distance >= maxRepulsionDistance) {
                            continue;
                        }

                        // Inverse-square repulsion
                        const force = repulsion / (distance * distance);
                        let forceX = (deltaX / distance) * force;
                        let forceY = (deltaY / distance) * force;

                        // Hard collision separation constraint: prevents circles from ever crushing together
                        const minSpacing = firstNode.radius + secondNode.radius + 16;
                        if (distance < minSpacing) {
                            const overlapRatio = (minSpacing - distance) / minSpacing;
                            const push = overlapRatio * 2.2;
                            forceX += (deltaX / distance) * push;
                            forceY += (deltaY / distance) * push;
                        }

                        firstNode.vx -= forceX;
                        firstNode.vy -= forceY;
                        secondNode.vx += forceX;
                        secondNode.vy += forceY;
                    }
                }

                // 2. Link spring forces
                const restLength = Math.max(75, Math.min(150, 70 + Math.sqrt(nodeCount) * 3));
                const springStrength = 0.032;
                for (const edge of this.edges) {
                    const source = this.nodes[edge.source];
                    const target = this.nodes[edge.target];
                    if (!source || !target) {
                        continue;
                    }

                    const deltaX = target.x - source.x;
                    const deltaY = target.y - source.y;
                    const distance = Math.hypot(deltaX, deltaY) || 1;
                    const force = (distance - restLength) * springStrength;
                    const forceX = (deltaX / distance) * force;
                    const forceY = (deltaY / distance) * force;
                    source.vx += forceX;
                    source.vy += forceY;
                    target.vx -= forceX;
                    target.vy -= forceY;
                }

                // 3. Gentle center gravity scaled inversely with graph size
                const centerGravity = Math.max(0.0003, 0.0018 / Math.sqrt(nodeCount));
                let fastestNodeSpeed = 0;

                for (const node of this.nodes) {
                    if (node === this.dragNode) {
                        node.vx = 0;
                        node.vy = 0;
                        continue;
                    }

                    node.vx -= node.x * centerGravity;
                    node.vy -= node.y * centerGravity;
                    node.vx *= 0.86;
                    node.vy *= 0.86;

                    const speed = Math.hypot(node.vx, node.vy);
                    if (speed > 20) {
                        node.vx = (node.vx / speed) * 20;
                        node.vy = (node.vy / speed) * 20;
                    }

                    node.x += node.vx;
                    node.y += node.vy;
                    fastestNodeSpeed = Math.max(fastestNodeSpeed, speed);
                }

                return fastestNodeSpeed;
            };

            this.frameHandler = () => {
                this.animId = null;
                if (this.destroyed) {
                    return;
                }

                if (this.simulationActive && !this.prefersReducedMotion) {
                    const fastestNodeSpeed = this.simulationStepHandler();
                    this.simulationTicks++;
                    this.stableFrameCount = fastestNodeSpeed < STABLE_SPEED_THRESHOLD
                        ? this.stableFrameCount + 1
                        : 0;

                    if (this.simulationTicks >= this.simulationTickLimit
                        || this.stableFrameCount >= STABLE_FRAME_LIMIT) {
                        this.simulationActive = false;
                        if (!this.userHasAdjustedView && !this.hasInitialFitted) {
                            this.hasInitialFitted = true;
                            this.fitToViewport();
                        }
                    }
                } else {
                    this.simulationActive = false;
                }

                this.drawHandler();

                if (this.simulationActive) {
                    this.requestRenderHandler();
                }
            };

            this.requestRenderHandler = () => {
                if (this.destroyed || this.animId !== null) {
                    return;
                }

                if (requestFrame) {
                    this.animId = requestFrame(this.frameHandler);
                } else {
                    this.simulationActive = false;
                    this.drawHandler();
                }
            };

            this.resizeHandler = () => {
                if (this.destroyed) {
                    return;
                }

                const rect = canvas.getBoundingClientRect();
                const width = Math.max(1, Math.round(rect.width || canvas.clientWidth || 600));
                const height = Math.max(1, Math.round(rect.height || canvas.clientHeight || 600));
                const nextPixelRatio = Math.max(1, Math.min(browserWindow?.devicePixelRatio || 1, 3));
                const previousWidth = this.viewportWidth;
                const previousHeight = this.viewportHeight;

                this.viewportWidth = width;
                this.viewportHeight = height;
                this.pixelRatio = nextPixelRatio;
                canvas.width = Math.round(width * nextPixelRatio);
                canvas.height = Math.round(height * nextPixelRatio);

                if (!this.hasCenteredView) {
                    this.panX = width / 2;
                    this.panY = height / 2;
                    this.hasCenteredView = true;
                } else if (previousWidth > 0 && previousHeight > 0) {
                    this.panX += (width - previousWidth) / 2;
                    this.panY += (height - previousHeight) / 2;
                }

                this.requestRenderHandler();
            };

            this.resizeHandler();

            const nodeCount = Math.max(1, this.nodes.length);
            const layoutRadius = Math.max(220, Math.sqrt(nodeCount) * 46);
            const orderedNodes = this.nodes
                .map((node, index) => ({ node, index }))
                .sort((first, second) => {
                    const firstKey = String(first.node.path ?? first.node.id ?? first.index);
                    const secondKey = String(second.node.path ?? second.node.id ?? second.index);

                    if (firstKey === secondKey) {
                        return first.index - second.index;
                    }

                    return firstKey < secondKey ? -1 : 1;
                });

            orderedNodes.forEach(({ node }, index) => {
                if (orderedNodes.length === 1) {
                    node.x = 0;
                    node.y = 0;
                } else {
                    const distance = layoutRadius * Math.sqrt((index + 1) / orderedNodes.length);
                    const angle = index * GOLDEN_ANGLE;
                    node.x = Math.cos(angle) * distance;
                    node.y = Math.sin(angle) * distance;
                }
                node.vx = 0;
                node.vy = 0;
            });

            // Initial auto-fit to comfortably frame the graph
            this.fitToViewport();

            const canvasPoint = (event) => {
                const rect = canvas.getBoundingClientRect();

                return {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top,
                };
            };

            const nodeAtPoint = (x, y) => {
                const worldX = (x - this.panX) / this.zoom;
                const worldY = (y - this.panY) / this.zoom;
                const scaleFactor = Math.pow(this.zoom, 0.45);

                return this.nodes.find((node) => {
                    const visualRadius = node.radius / scaleFactor;
                    return Math.hypot(node.x - worldX, node.y - worldY) < visualRadius + (8 / this.zoom);
                }) ?? null;
            };

            const releasePointer = (event) => {
                if (typeof canvas.hasPointerCapture === 'function'
                    && canvas.hasPointerCapture(event.pointerId)
                    && typeof canvas.releasePointerCapture === 'function') {
                    try {
                        canvas.releasePointerCapture(event.pointerId);
                    } catch {
                        // Pointer capture may already have been released by the browser.
                    }
                }
            };

            const finishPointerInteraction = (event, shouldSelect) => {
                if (this.activePointerId !== event.pointerId) {
                    return;
                }

                const selectedNode = shouldSelect && !this.hasDragged ? this.pointerDownNode : null;
                const dragged = this.hasDragged;
                releasePointer(event);
                this.isDragging = false;
                this.dragNode = null;
                this.pointerDownNode = null;
                this.activePointerId = null;
                this.hasDragged = false;

                if (dragged) {
                    this.startSimulation(INTERACTION_SIMULATION_TICKS);
                } else {
                    this.requestRender();
                }

                if (selectedNode) {
                    void this.selectNode(selectedNode);
                }
            };

            this.eventHandlers = {
                pointermove: (event) => {
                    const point = canvasPoint(event);

                    if (this.activePointerId === event.pointerId && this.isDragging) {
                        const totalDeltaX = point.x - this.pointerStartX;
                        const totalDeltaY = point.y - this.pointerStartY;
                        this.hasDragged = this.hasDragged
                            || Math.hypot(totalDeltaX, totalDeltaY) >= this.dragThreshold;

                        if (this.hasDragged) {
                            this.userHasAdjustedView = true;
                            if (this.dragNode) {
                                this.dragNode.x = this.dragOriginX + totalDeltaX / this.zoom;
                                this.dragNode.y = this.dragOriginY + totalDeltaY / this.zoom;
                                this.dragNode.vx = 0;
                                this.dragNode.vy = 0;
                            } else {
                                this.panX = this.dragOriginX + totalDeltaX;
                                this.panY = this.dragOriginY + totalDeltaY;
                            }
                        }
                    } else {
                        this.hoveredNode = nodeAtPoint(point.x, point.y);
                    }

                    this.tooltipX = point.x;
                    this.tooltipY = point.y;
                    this.lastMouseX = point.x;
                    this.lastMouseY = point.y;
                    this.requestRender();
                },
                pointerdown: (event) => {
                    if (event.isPrimary === false || (event.pointerType === 'mouse' && event.button !== 0)) {
                        return;
                    }

                    const point = canvasPoint(event);
                    const node = nodeAtPoint(point.x, point.y);
                    this.activePointerId = event.pointerId;
                    this.isDragging = true;
                    this.hasDragged = false;
                    this.pointerDownNode = node;
                    this.dragNode = node;
                    this.pointerStartX = point.x;
                    this.pointerStartY = point.y;
                    this.lastMouseX = point.x;
                    this.lastMouseY = point.y;
                    this.dragOriginX = node ? node.x : this.panX;
                    this.dragOriginY = node ? node.y : this.panY;

                    if (typeof canvas.setPointerCapture === 'function') {
                        try {
                            canvas.setPointerCapture(event.pointerId);
                        } catch {
                            // Some synthetic events and older browsers cannot capture pointers.
                        }
                    }
                },
                pointerup: (event) => finishPointerInteraction(event, true),
                pointercancel: (event) => finishPointerInteraction(event, false),
                lostpointercapture: (event) => {
                    if (this.activePointerId !== event.pointerId) {
                        return;
                    }

                    this.isDragging = false;
                    this.dragNode = null;
                    this.pointerDownNode = null;
                    this.activePointerId = null;
                    this.hasDragged = false;
                    this.requestRender();
                },
                pointerleave: () => {
                    if (this.activePointerId === null) {
                        this.hoveredNode = null;
                        this.requestRender();
                    }
                },
                wheel: (event) => {
                    event.preventDefault();
                    const point = canvasPoint(event);
                    const zoomFactor = event.deltaY < 0 ? 1.1 : 0.9;
                    const newZoom = Math.max(0.2, Math.min(4, this.zoom * zoomFactor));
                    if (newZoom !== this.zoom) {
                        this.userHasAdjustedView = true;
                        this.panX = point.x - (point.x - this.panX) * (newZoom / this.zoom);
                        this.panY = point.y - (point.y - this.panY) * (newZoom / this.zoom);
                        this.zoom = newZoom;
                        this.requestRender();
                    }
                },
            };

            for (const [eventName, handler] of Object.entries(this.eventHandlers)) {
                canvas.addEventListener(eventName, handler, eventName === 'wheel' ? { passive: false } : undefined);
            }

            this.canvasCleanupHandler = () => {
                for (const [eventName, handler] of Object.entries(this.eventHandlers || {})) {
                    canvas.removeEventListener(eventName, handler);
                }

                if (this.activePointerId !== null
                    && typeof canvas.hasPointerCapture === 'function'
                    && canvas.hasPointerCapture(this.activePointerId)
                    && typeof canvas.releasePointerCapture === 'function') {
                    try {
                        canvas.releasePointerCapture(this.activePointerId);
                    } catch {
                        // Pointer capture may already have been released during teardown.
                    }
                }

                if (canvas.style && this.previousTouchAction !== null) {
                    canvas.style.touchAction = this.previousTouchAction;
                }
            };

            if (typeof browserWindow?.addEventListener === 'function') {
                browserWindow.addEventListener('resize', this.resizeHandler);
            }

            const ResizeObserverClass = typeof ResizeObserver !== 'undefined'
                ? ResizeObserver
                : browserWindow?.ResizeObserver;
            if (ResizeObserverClass) {
                this.resizeObserver = new ResizeObserverClass(this.resizeHandler);
                this.resizeObserver.observe(canvas);
            }

            if (this.motionQuery) {
                this.motionChangeHandler = (event) => {
                    this.prefersReducedMotion = event.matches;
                    if (this.prefersReducedMotion) {
                        this.simulationActive = false;
                        if (this.animId !== null && cancelFrame) {
                            cancelFrame(this.animId);
                            this.animId = null;
                        }
                        this.requestRender();
                    } else {
                        this.startSimulation(DEFAULT_SIMULATION_TICKS);
                    }
                };

                if (typeof this.motionQuery.addEventListener === 'function') {
                    this.motionQuery.addEventListener('change', this.motionChangeHandler);
                } else if (typeof this.motionQuery.addListener === 'function') {
                    this.motionQuery.addListener(this.motionChangeHandler);
                }
            }

            if (typeof this.$watch === 'function') {
                const cleanup = this.$watch('search', () => this.requestRender());
                if (typeof cleanup === 'function') {
                    this.searchWatcherCleanup = cleanup;
                }
            }

            if (this.prefersReducedMotion || this.nodes.length < 2) {
                this.requestRender();
            } else {
                this.startSimulation(DEFAULT_SIMULATION_TICKS);
            }
        },

        destroy() {
            this.destroyed = true;
            this.simulationActive = false;

            const browserWindow = typeof window !== 'undefined' ? window : null;
            if (this.animId !== null && browserWindow?.cancelAnimationFrame) {
                browserWindow.cancelAnimationFrame(this.animId);
            }
            this.animId = null;

            if (this.resizeHandler && browserWindow?.removeEventListener) {
                browserWindow.removeEventListener('resize', this.resizeHandler);
            }

            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }

            if (this.motionQuery && this.motionChangeHandler) {
                if (typeof this.motionQuery.removeEventListener === 'function') {
                    this.motionQuery.removeEventListener('change', this.motionChangeHandler);
                } else if (typeof this.motionQuery.removeListener === 'function') {
                    this.motionQuery.removeListener(this.motionChangeHandler);
                }
            }

            this.canvasCleanupHandler?.();

            if (typeof this.searchWatcherCleanup === 'function') {
                this.searchWatcherCleanup();
            }

            this.resizeHandler = null;
            this.eventHandlers = null;
            this.canvasCleanupHandler = null;
            this.motionQuery = null;
            this.motionChangeHandler = null;
            this.searchWatcherCleanup = null;
            this.requestRenderHandler = null;
            this.drawHandler = null;
            this.frameHandler = null;
            this.simulationStepHandler = null;
            this.isDragging = false;
            this.dragNode = null;
            this.pointerDownNode = null;
            this.activePointerId = null;
            this.hasDragged = false;
        },

        requestRender() {
            this.requestRenderHandler?.();
        },

        startSimulation(tickLimit = DEFAULT_SIMULATION_TICKS) {
            this.simulationTicks = 0;
            this.simulationTickLimit = tickLimit;
            this.stableFrameCount = 0;
            this.simulationActive = !this.prefersReducedMotion && this.nodes.length > 1;
            this.requestRender();
        },

        async selectNode(nodeOrId) {
            const node = typeof nodeOrId === 'object'
                ? nodeOrId
                : this.nodes.find((candidate) => String(candidate.id) === String(nodeOrId));

            if (!node) {
                return false;
            }

            try {
                if (typeof this.$wire?.openFileInEditor === 'function') {
                    await this.$wire.openFileInEditor(node.id);
                } else if (typeof this.$wire?.selectNote === 'function') {
                    await this.$wire.selectNote(node.path || node.name);
                    if (typeof this.$wire?.switchLayout === 'function') {
                        this.$wire.switchLayout('docs');
                    }
                    if (this.$wire && 'graphModalOpen' in this.$wire) {
                        this.$wire.graphModalOpen = false;
                    }
                } else if (typeof this.$wire?.selectFile === 'function') {
                    await this.$wire.selectFile(node.id);
                    this.$wire.activeTab = 'editor';
                } else {
                    return false;
                }

                const browserWindow = typeof window !== 'undefined' ? window : null;
                const pageDocument = typeof document !== 'undefined' ? document : null;
                browserWindow?.requestAnimationFrame?.(() => {
                    pageDocument?.querySelector('[data-vault-editor]')?.scrollIntoView({
                        behavior: browserWindow.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                        block: 'start',
                    });
                });

                return true;
            } catch {
                return false;
            }
        },

        async handleNodeKeydown(event, nodeOrId) {
            if (!['Enter', ' '].includes(event.key)) {
                return false;
            }

            event.preventDefault();

            return this.selectNode(nodeOrId);
        },

        fitToViewport(paddingRatio = 0.85) {
            if (this.nodes.length === 0) {
                this.zoom = 1;
                this.panX = (this.viewportWidth || 600) / 2;
                this.panY = (this.viewportHeight || 600) / 2;
                return;
            }

            let minX = Infinity;
            let maxX = -Infinity;
            let minY = Infinity;
            let maxY = -Infinity;

            for (const node of this.nodes) {
                const r = node.radius || 8;
                if (node.x - r < minX) minX = node.x - r;
                if (node.x + r > maxX) maxX = node.x + r;
                if (node.y - r < minY) minY = node.y - r;
                if (node.y + r > maxY) maxY = node.y + r;
            }

            const width = this.viewportWidth || 600;
            const height = this.viewportHeight || 600;
            const graphWidth = Math.max(100, maxX - minX);
            const graphHeight = Math.max(100, maxY - minY);
            const centerX = (minX + maxX) / 2;
            const centerY = (minY + maxY) / 2;

            const scaleX = (width * paddingRatio) / graphWidth;
            const scaleY = (height * paddingRatio) / graphHeight;
            const fitZoom = Math.max(0.2, Math.min(1.25, Math.min(scaleX, scaleY)));

            this.zoom = Number(fitZoom.toFixed(3));
            this.panX = Math.round((width / 2) - (centerX * this.zoom));
            this.panY = Math.round((height / 2) - (centerY * this.zoom));
            this.requestRender();
        },

        zoomIn() {
            const centerX = this.viewportWidth > 0 ? this.viewportWidth / 2 : this.panX;
            const centerY = this.viewportHeight > 0 ? this.viewportHeight / 2 : this.panY;
            const newZoom = Math.min(4, this.zoom * 1.25);
            if (newZoom !== this.zoom) {
                this.userHasAdjustedView = true;
                this.panX = centerX - (centerX - this.panX) * (newZoom / this.zoom);
                this.panY = centerY - (centerY - this.panY) * (newZoom / this.zoom);
                this.zoom = newZoom;
                this.requestRender();
            }
        },

        zoomOut() {
            const centerX = this.viewportWidth > 0 ? this.viewportWidth / 2 : this.panX;
            const centerY = this.viewportHeight > 0 ? this.viewportHeight / 2 : this.panY;
            const newZoom = Math.max(0.2, this.zoom * 0.8);
            if (newZoom !== this.zoom) {
                this.userHasAdjustedView = true;
                this.panX = centerX - (centerX - this.panX) * (newZoom / this.zoom);
                this.panY = centerY - (centerY - this.panY) * (newZoom / this.zoom);
                this.zoom = newZoom;
                this.requestRender();
            }
        },

        resetView() {
            this.search = '';
            this.userHasAdjustedView = false;
            if (this.viewportWidth > 0 && this.nodes.length > 0) {
                this.fitToViewport();
            } else {
                this.zoom = 1;
                this.panX = (this.viewportWidth || 0) / 2;
                this.panY = (this.viewportHeight || 0) / 2;
                this.hasCenteredView = false;
                this.requestRender();
            }
        },
    };
}

if (typeof window !== 'undefined') {
    window.vaultGraph = createVaultGraph;
}
