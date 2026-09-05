export function createVaultGraph(data = {}) {
    return {
        nodes: (data.nodes || []).map((node, i) => ({
            ...node,
            x: node.x ?? 0,
            y: node.y ?? 0,
            vx: 0,
            vy: 0,
            radius: 4 + Math.min((node.linksCount || 0) * 2, 10),
        })),
        edges: data.edges || [],
        search: '',
        hoveredNode: null,
        tooltipX: 0,
        tooltipY: 0,
        zoom: 1,
        panX: 0,
        panY: 0,
        isDragging: false,
        dragNode: null,
        lastMouseX: 0,
        lastMouseY: 0,
        animId: null,
        resizeHandler: null,

        init() {
            const canvas = this.$refs?.graphCanvas;
            if (!canvas || typeof canvas.getContext !== 'function') {
                return;
            }

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return;
            }

            this.resizeHandler = () => {
                if (!canvas) return;
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width;
                canvas.height = rect.height;
                if (this.panX === 0 && this.panY === 0) {
                    this.panX = canvas.width / 2;
                    this.panY = canvas.height / 2;
                }
            };

            this.resizeHandler();
            if (typeof window !== 'undefined') {
                window.addEventListener('resize', this.resizeHandler);
            }

            const radius = Math.min(canvas.width || 600, canvas.height || 600) * 0.35;
            this.nodes.forEach((node, i) => {
                const angle = (i / Math.max(1, this.nodes.length)) * Math.PI * 2;
                node.x = Math.cos(angle) * radius + (Math.random() - 0.5) * 40;
                node.y = Math.sin(angle) * radius + (Math.random() - 0.5) * 40;
                node.vx = 0;
                node.vy = 0;
            });

            canvas.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                const mx = e.clientX - rect.left;
                const my = e.clientY - rect.top;

                if (this.isDragging) {
                    const dx = mx - this.lastMouseX;
                    const dy = my - this.lastMouseY;
                    if (this.dragNode) {
                        this.dragNode.x += dx / this.zoom;
                        this.dragNode.y += dy / this.zoom;
                    } else {
                        this.panX += dx;
                        this.panY += dy;
                    }
                    this.lastMouseX = mx;
                    this.lastMouseY = my;
                    return;
                }

                const worldX = (mx - this.panX) / this.zoom;
                const worldY = (my - this.panY) / this.zoom;
                let found = null;

                for (let node of this.nodes) {
                    const dist = Math.hypot(node.x - worldX, node.y - worldY);
                    if (dist < node.radius + 6) {
                        found = node;
                        break;
                    }
                }

                this.hoveredNode = found;
                this.tooltipX = mx;
                this.tooltipY = my;
                this.lastMouseX = mx;
                this.lastMouseY = my;
            });

            canvas.addEventListener('mousedown', (e) => {
                const rect = canvas.getBoundingClientRect();
                const mx = e.clientX - rect.left;
                const my = e.clientY - rect.top;
                const worldX = (mx - this.panX) / this.zoom;
                const worldY = (my - this.panY) / this.zoom;

                this.isDragging = true;
                this.lastMouseX = mx;
                this.lastMouseY = my;

                for (let node of this.nodes) {
                    const dist = Math.hypot(node.x - worldX, node.y - worldY);
                    if (dist < node.radius + 6) {
                        this.dragNode = node;
                        return;
                    }
                }
                this.dragNode = null;
            });

            canvas.addEventListener('mouseup', () => {
                this.isDragging = false;
                this.dragNode = null;
            });

            canvas.addEventListener('click', (e) => {
                const rect = canvas.getBoundingClientRect();
                const mx = e.clientX - rect.left;
                const my = e.clientY - rect.top;
                const worldX = (mx - this.panX) / this.zoom;
                const worldY = (my - this.panY) / this.zoom;

                for (let node of this.nodes) {
                    const dist = Math.hypot(node.x - worldX, node.y - worldY);
                    if (dist < node.radius + 6) {
                        if (this.$wire?.selectFile) {
                            this.$wire.selectFile(node.id);
                        }
                        if (this.$wire) {
                            this.$wire.activeTab = 'editor';
                        }
                        return;
                    }
                }
            });

            canvas.addEventListener('wheel', (e) => {
                e.preventDefault();
                const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
                this.zoom = Math.max(0.3, Math.min(3.0, this.zoom * zoomFactor));
            }, { passive: false });

            const tick = () => {
                if (!canvas || !ctx) return;

                const kRepulse = 1200;
                for (let i = 0; i < this.nodes.length; i++) {
                    for (let j = i + 1; j < this.nodes.length; j++) {
                        const n1 = this.nodes[i];
                        const n2 = this.nodes[j];
                        const dx = n2.x - n1.x;
                        const dy = n2.y - n1.y;
                        const dist = Math.hypot(dx, dy) || 1;
                        if (dist < 300) {
                            const force = kRepulse / (dist * dist);
                            const fx = (dx / dist) * force;
                            const fy = (dy / dist) * force;
                            n1.vx -= fx;
                            n1.vy -= fy;
                            n2.vx += fx;
                            n2.vy += fy;
                        }
                    }
                }

                const kSpring = 0.04;
                const restLength = 80;
                for (let edge of this.edges) {
                    const n1 = this.nodes[edge.source];
                    const n2 = this.nodes[edge.target];
                    if (!n1 || !n2) continue;
                    const dx = n2.x - n1.x;
                    const dy = n2.y - n1.y;
                    const dist = Math.hypot(dx, dy) || 1;
                    const force = (dist - restLength) * kSpring;
                    const fx = (dx / dist) * force;
                    const fy = (dy / dist) * force;
                    n1.vx -= fx;
                    n1.vy -= fy;
                    n2.vx += fx;
                    n2.vy += fy;
                }

                this.nodes.forEach(node => {
                    if (node === this.dragNode) return;
                    node.vx -= node.x * 0.005;
                    node.vy -= node.y * 0.005;
                    node.vx *= 0.88;
                    node.vy *= 0.88;
                    node.x += node.vx;
                    node.y += node.vy;
                });

                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.save();
                ctx.translate(this.panX, this.panY);
                ctx.scale(this.zoom, this.zoom);

                for (let edge of this.edges) {
                    const n1 = this.nodes[edge.source];
                    const n2 = this.nodes[edge.target];
                    if (!n1 || !n2) continue;

                    const isConnectedToHover = this.hoveredNode && (this.hoveredNode === n1 || this.hoveredNode === n2);

                    ctx.beginPath();
                    ctx.moveTo(n1.x, n1.y);
                    ctx.lineTo(n2.x, n2.y);
                    if (isConnectedToHover) {
                        ctx.strokeStyle = 'rgba(16, 185, 129, 0.7)';
                        ctx.lineWidth = 2;
                    } else {
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
                        ctx.lineWidth = 1;
                    }
                    ctx.stroke();
                }

                for (let node of this.nodes) {
                    const isMatch = !this.search || (node.name && node.name.toLowerCase().includes(this.search.toLowerCase()));
                    const isHovered = (this.hoveredNode === node);

                    ctx.beginPath();
                    ctx.arc(node.x, node.y, node.radius * (isHovered ? 1.3 : 1), 0, Math.PI * 2);

                    if (isHovered) {
                        ctx.fillStyle = '#10B981';
                        ctx.shadowColor = '#10B981';
                        ctx.shadowBlur = 12;
                    } else if (isMatch) {
                        ctx.fillStyle = (node.linksCount || 0) > 2 ? '#059669' : '#4B5563';
                        ctx.shadowBlur = 0;
                    } else {
                        ctx.fillStyle = 'rgba(75, 85, 99, 0.2)';
                        ctx.shadowBlur = 0;
                    }
                    ctx.fill();

                    if (isMatch || isHovered) {
                        ctx.font = isHovered ? 'bold 11px sans-serif' : '10px sans-serif';
                        ctx.fillStyle = isHovered ? '#FFFFFF' : 'rgba(209, 213, 219, 0.75)';
                        ctx.textAlign = 'center';
                        ctx.fillText(node.name || '', node.x, node.y + node.radius + 12);
                    }
                }

                ctx.restore();
                if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
                    this.animId = window.requestAnimationFrame(tick);
                }
            };

            if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
                this.animId = window.requestAnimationFrame(tick);
            }
        },

        destroy() {
            if (this.animId && typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function') {
                window.cancelAnimationFrame(this.animId);
                this.animId = null;
            }

            if (this.resizeHandler && typeof window !== 'undefined') {
                window.removeEventListener('resize', this.resizeHandler);
                this.resizeHandler = null;
            }
        },

        zoomIn() {
            this.zoom = Math.min(3.0, this.zoom * 1.2);
        },

        zoomOut() {
            this.zoom = Math.max(0.3, this.zoom * 0.8);
        },

        resetView() {
            const canvas = this.$refs?.graphCanvas;
            this.zoom = 1;
            if (canvas) {
                this.panX = canvas.width / 2;
                this.panY = canvas.height / 2;
            } else {
                this.panX = 0;
                this.panY = 0;
            }
            this.search = '';
        },
    };
}

if (typeof window !== 'undefined') {
    window.vaultGraph = createVaultGraph;
}
