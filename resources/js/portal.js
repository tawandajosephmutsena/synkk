/**
 * Synkk Portal Interactive Engine
 * High-craftsmanship client interactions for published Obsidian vaults.
 */

// 1. One-Click Code Copying with Visual Feedback
window.copyCode = function (button) {
    if (!button) return;
    const codeWindow = button.closest('.synkk-code-window') || button.closest('pre');
    const codeEl = codeWindow ? codeWindow.querySelector('code') : null;
    if (!codeEl) return;

    const text = codeEl.innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        const originalContent = button.innerHTML;
        button.innerHTML = `
            <svg class="size-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span class="text-emerald-400">Copied!</span>
        `;
        button.classList.add('border-emerald-500/40', 'bg-emerald-500/10');

        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('border-emerald-500/40', 'bg-emerald-500/10');
        }, 2000);
    }).catch(err => {
        console.warn('Failed to copy code:', err);
    });
};

// 2. Bento Card Cursor Spotlight Tracker
function initSpotlightCards() {
    const cards = document.querySelectorAll('.synkk-bento-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });
}

// 3. Table of Contents Scrollspy
function initTocScrollspy() {
    const tocLinks = document.querySelectorAll('.synkk-toc-link');
    if (!tocLinks.length) return;

    const headings = Array.from(document.querySelectorAll('.synkk-prose h1[id], .synkk-prose h2[id], .synkk-prose h3[id], .synkk-prose h4[id]'));
    if (!headings.length) return;

    function onScroll() {
        const scrollPos = window.scrollY + 120;
        let currentId = null;

        for (let i = 0; i < headings.length; i++) {
            const heading = headings[i];
            if (heading.offsetTop <= scrollPos) {
                currentId = heading.id;
            } else {
                break;
            }
        }

        tocLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === `#${currentId}`) {
                link.classList.add('is-active');
            } else {
                link.classList.remove('is-active');
            }
        });
    }

    window.removeEventListener('scroll', onScroll);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// 4. Portal Theme Switcher
window.setPortalTheme = function (themeName) {
    const wrapper = document.querySelector('.synkk-portal-wrapper') || document.documentElement;
    if (!wrapper) return;

    wrapper.setAttribute('data-portal-theme', themeName);
    localStorage.setItem('synkk-portal-theme', themeName);

    // Update checkmark or indicator in dropdown if present
    document.querySelectorAll('[data-theme-choice]').forEach(btn => {
        const isCurrent = btn.getAttribute('data-theme-choice') === themeName;
        btn.classList.toggle('ring-1', isCurrent);
        btn.classList.toggle('ring-amber-500', isCurrent);
    });
};

function restorePortalTheme() {
    const saved = localStorage.getItem('synkk-portal-theme');
    if (saved) {
        window.setPortalTheme(saved);
    }
}

// 5. Interactive 2D Canvas Physics Knowledge Graph
window.SynkkGraph = {
    activeSimulation: null,

    init(canvasEl, graphData, onNodeClick) {
        if (!canvasEl || !graphData || !graphData.nodes || !graphData.nodes.length) {
            return null;
        }

        if (this.activeSimulation) {
            this.activeSimulation.destroy();
        }

        const ctx = canvasEl.getContext('2d');
        const dpr = window.devicePixelRatio || 1;

        let width = canvasEl.clientWidth || 800;
        let height = canvasEl.clientHeight || 500;

        canvasEl.width = width * dpr;
        canvasEl.height = height * dpr;
        ctx.scale(dpr, dpr);

        // Simulation parameters
        const nodes = graphData.nodes.map((n, i) => {
            const angle = (i / graphData.nodes.length) * 2 * Math.PI;
            const dist = 100 + Math.random() * 80;
            return {
                id: n.id,
                name: n.name || n.title || 'Note',
                path: n.path || '',
                linksCount: n.links_count || n.linksCount || 1,
                radius: Math.min(18, Math.max(7, (n.links_count || 1) * 2.5 + 4)),
                x: width / 2 + Math.cos(angle) * dist + (Math.random() - 0.5) * 40,
                y: height / 2 + Math.sin(angle) * dist + (Math.random() - 0.5) * 40,
                vx: 0,
                vy: 0,
                color: n.color || (n.links_count > 3 ? '#f59e0b' : '#38bdf8'),
            };
        });

        const nodeMap = new Map(nodes.map(n => [n.id, n]));

        const edges = (graphData.edges || []).map(e => {
            return {
                source: nodeMap.get(e.source || e.source_id),
                target: nodeMap.get(e.target || e.target_id),
            };
        }).filter(e => e.source && e.target);

        // Pan & zoom state
        let scale = 1;
        let panX = 0;
        let panY = 0;
        let isPanning = false;
        let startPanX = 0;
        let startPanY = 0;

        let hoveredNode = null;
        let draggedNode = null;
        let isRunning = true;
        let animFrameId = null;

        function getMousePos(e) {
            const rect = canvasEl.getBoundingClientRect();
            const clientX = e.clientX - rect.left;
            const clientY = e.clientY - rect.top;
            return {
                x: (clientX - panX) / scale,
                y: (clientY - panY) / scale,
                rawX: clientX,
                rawY: clientY,
            };
        }

        function findNodeAt(pos) {
            for (let i = nodes.length - 1; i >= 0; i--) {
                const node = nodes[i];
                const dx = pos.x - node.x;
                const dy = pos.y - node.y;
                if (dx * dx + dy * dy <= (node.radius + 6) * (node.radius + 6)) {
                    return node;
                }
            }
            return null;
        }

        // Physics step
        function stepPhysics() {
            const repulsion = 400;
            const springLength = 80;
            const springK = 0.04;
            const centerGravity = 0.015;
            const damping = 0.86;

            const cx = width / 2;
            const cy = height / 2;

            // Repulsion between all node pairs
            for (let i = 0; i < nodes.length; i++) {
                const n1 = nodes[i];
                for (let j = i + 1; j < nodes.length; j++) {
                    const n2 = nodes[j];
                    let dx = n2.x - n1.x;
                    let dy = n2.y - n1.y;
                    let distSq = dx * dx + dy * dy;
                    if (distSq < 1) distSq = 1;
                    const dist = Math.sqrt(distSq);

                    const force = (repulsion / distSq);
                    const fx = (dx / dist) * force;
                    const fy = (dy / dist) * force;

                    n1.vx -= fx;
                    n1.vy -= fy;
                    n2.vx += fx;
                    n2.vy += fy;
                }

                // Center gravity
                n1.vx += (cx - n1.x) * centerGravity;
                n1.vy += (cy - n1.y) * centerGravity;
            }

            // Spring forces along edges
            for (let k = 0; k < edges.length; k++) {
                const e = edges[k];
                const dx = e.target.x - e.source.x;
                const dy = e.target.y - e.source.y;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;
                const displacement = dist - springLength;
                const force = displacement * springK;

                const fx = (dx / dist) * force;
                const fy = (dy / dist) * force;

                e.source.vx += fx;
                e.source.vy += fy;
                e.target.vx -= fx;
                e.target.vy -= fy;
            }

            // Apply velocities
            for (let i = 0; i < nodes.length; i++) {
                const n = nodes[i];
                if (n === draggedNode) continue;

                n.vx *= damping;
                n.vy *= damping;
                n.x += n.vx;
                n.y += n.vy;

                // Soft boundary constraint
                const pad = n.radius + 10;
                if (n.x < pad) { n.x = pad; n.vx *= -0.5; }
                if (n.x > width - pad) { n.x = width - pad; n.vx *= -0.5; }
                if (n.y < pad) { n.y = pad; n.vy *= -0.5; }
                if (n.y > height - pad) { n.y = height - pad; n.vy *= -0.5; }
            }
        }

        // Render step
        function render() {
            if (!isRunning) return;

            stepPhysics();

            ctx.save();
            ctx.clearRect(0, 0, width, height);

            ctx.translate(panX, panY);
            ctx.scale(scale, scale);

            // Draw edges
            for (let i = 0; i < edges.length; i++) {
                const e = edges[i];
                const isHighlighted = hoveredNode && (e.source === hoveredNode || e.target === hoveredNode);

                ctx.beginPath();
                ctx.moveTo(e.source.x, e.source.y);
                ctx.lineTo(e.target.x, e.target.y);
                ctx.strokeStyle = isHighlighted ? 'rgba(245, 158, 11, 0.7)' : 'rgba(255, 255, 255, 0.08)';
                ctx.lineWidth = isHighlighted ? 2 : 1;
                ctx.stroke();
            }

            // Draw nodes
            for (let i = 0; i < nodes.length; i++) {
                const n = nodes[i];
                const isHovered = n === hoveredNode;

                // Outer glow if hovered
                if (isHovered) {
                    ctx.beginPath();
                    ctx.arc(n.x, n.y, n.radius + 6, 0, 2 * Math.PI);
                    ctx.fillStyle = 'rgba(245, 158, 11, 0.25)';
                    ctx.fill();
                }

                // Node circle
                ctx.beginPath();
                ctx.arc(n.x, n.y, n.radius, 0, 2 * Math.PI);
                ctx.fillStyle = isHovered ? '#f59e0b' : n.color;
                ctx.shadowColor = isHovered ? '#f59e0b' : 'rgba(0, 0, 0, 0.5)';
                ctx.shadowBlur = isHovered ? 12 : 4;
                ctx.fill();
                ctx.shadowBlur = 0;

                // Inner highlight ring
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
                ctx.lineWidth = 1.5;
                ctx.stroke();

                // Label
                ctx.font = isHovered ? '600 11px Inter, sans-serif' : '500 10px Inter, sans-serif';
                ctx.fillStyle = isHovered ? '#ffffff' : 'rgba(244, 244, 245, 0.75)';
                ctx.textAlign = 'center';
                ctx.fillText(n.name, n.x, n.y + n.radius + 14);
            }

            ctx.restore();

            // Draw Tooltip over canvas if hovered
            if (hoveredNode) {
                const screenX = hoveredNode.x * scale + panX;
                const screenY = hoveredNode.y * scale + panY;

                ctx.save();
                const tipText = `${hoveredNode.name} (${hoveredNode.linksCount} connection${hoveredNode.linksCount === 1 ? '' : 's'})`;
                ctx.font = '600 11px Inter, sans-serif';
                const textWidth = ctx.measureText(tipText).width;
                const padX = 10;
                const padY = 6;
                const boxW = textWidth + padX * 2;
                const boxH = 24;
                const boxX = screenX - boxW / 2;
                const boxY = screenY - hoveredNode.radius * scale - 36;

                // Tooltip background
                ctx.fillStyle = 'rgba(15, 15, 20, 0.95)';
                ctx.strokeStyle = 'rgba(245, 158, 11, 0.5)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.roundRect(boxX, boxY, boxW, boxH, 6);
                ctx.fill();
                ctx.stroke();

                // Tooltip text
                ctx.fillStyle = '#f59e0b';
                ctx.textAlign = 'center';
                ctx.fillText(tipText, screenX, boxY + 16);
                ctx.restore();
            }

            animFrameId = requestAnimationFrame(render);
        }

        // Event Listeners
        function onMouseDown(e) {
            const pos = getMousePos(e);
            const hit = findNodeAt(pos);

            if (hit) {
                draggedNode = hit;
                canvasEl.style.cursor = 'grabbing';
            } else {
                isPanning = true;
                startPanX = e.clientX - panX;
                startPanY = e.clientY - panY;
                canvasEl.style.cursor = 'move';
            }
        }

        function onMouseMove(e) {
            const pos = getMousePos(e);

            if (draggedNode) {
                draggedNode.x = pos.x;
                draggedNode.y = pos.y;
                draggedNode.vx = 0;
                draggedNode.vy = 0;
                return;
            }

            if (isPanning) {
                panX = e.clientX - startPanX;
                panY = e.clientY - startPanY;
                return;
            }

            const hit = findNodeAt(pos);
            if (hit !== hoveredNode) {
                hoveredNode = hit;
                canvasEl.style.cursor = hit ? 'pointer' : 'default';
            }
        }

        function onMouseUp(e) {
            if (draggedNode) {
                const pos = getMousePos(e);
                const dx = pos.x - draggedNode.x;
                const dy = pos.y - draggedNode.y;
                if (Math.abs(dx) < 4 && Math.abs(dy) < 4 && typeof onNodeClick === 'function') {
                    onNodeClick(draggedNode);
                }
                draggedNode = null;
            }
            isPanning = false;
            canvasEl.style.cursor = hoveredNode ? 'pointer' : 'default';
        }

        function onWheel(e) {
            e.preventDefault();
            const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
            const newScale = Math.min(2.5, Math.max(0.4, scale * zoomFactor));

            const rect = canvasEl.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            panX = mouseX - (mouseX - panX) * (newScale / scale);
            panY = mouseY - (mouseY - panY) * (newScale / scale);
            scale = newScale;
        }

        canvasEl.addEventListener('mousedown', onMouseDown);
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
        canvasEl.addEventListener('wheel', onWheel, { passive: false });

        // Resize observer
        const resizeObserver = new ResizeObserver(() => {
            width = canvasEl.clientWidth || 800;
            height = canvasEl.clientHeight || 500;
            canvasEl.width = width * dpr;
            canvasEl.height = height * dpr;
            ctx.scale(dpr, dpr);
        });
        resizeObserver.observe(canvasEl);

        // Start render
        render();

        const sim = {
            destroy() {
                isRunning = false;
                if (animFrameId) cancelAnimationFrame(animFrameId);
                canvasEl.removeEventListener('mousedown', onMouseDown);
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                canvasEl.removeEventListener('wheel', onWheel);
                resizeObserver.disconnect();
            }
        };

        this.activeSimulation = sim;
        return sim;
    }
};

// Lifecycle Bootstrapping
function initPortal() {
    restorePortalTheme();
    initSpotlightCards();
    initTocScrollspy();
}

document.addEventListener('DOMContentLoaded', initPortal);
document.addEventListener('livewire:navigated', initPortal);
