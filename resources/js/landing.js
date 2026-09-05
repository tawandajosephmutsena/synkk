import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import './landing-showcase.js';
import { initializeMobileMenu, initializeSurfaceShowcase } from './landing-controls.js';

gsap.registerPlugin(ScrollTrigger);

const page = document.querySelector('.synkk-site');

if (page) {
    let hasEntered = false;
    const media = gsap.matchMedia();
    media.add({ desktop: '(min-width: 801px)', mobile: '(max-width: 800px)' }, (context) => {
        const { desktop } = context.conditions;

        if (!hasEntered && window.scrollY < 80) {
            const intro = gsap.timeline({ defaults: { ease: 'power3.out', duration: 1.1 } });

            intro
                .from('.synkk-hero__line > span', { yPercent: 110, stagger: 0.11 }, 0.1)
                .from('.synkk-hero__copy .synkk-eyebrow', { opacity: 0, y: 14, duration: 0.7 }, 0)
                .from(
                    '.synkk-hero__lede, .synkk-hero__actions, .synkk-hero__proof',
                    { opacity: 0, y: 18, stagger: 0.1 },
                    0.45,
                )
                .from('.synkk-hero__media', { opacity: 0, y: 30, scale: 0.97 }, 0.1)
                .from('[data-hero-layer]', { opacity: 0, y: 35, stagger: 0.15, duration: 1.35 }, 0.35)
                .from('.synkk-hero__line--accent svg', { scale: 0.5, rotate: -70, opacity: 0 }, 0.5);
        }

        hasEntered = true;

        gsap.utils.toArray('.synkk-section-heading, .synkk-roadmap__intro').forEach((heading) => {
            gsap.from(heading.children, {
                y: 24,
                duration: 0.9,
                stagger: 0.1,
                ease: 'power3.out',
                scrollTrigger: { trigger: heading, start: 'top 90%', once: true },
            });
        });

        gsap.utils
            .toArray(
                '.synkk-surfaces, .synkk-feature-card, .synkk-safety-card, .synkk-pricing-grid article, .synkk-roadmap-list li, .synkk-capability-strip article',
            )
            .forEach((card) => {
                gsap.from(card, {
                    y: 35,
                    duration: 1,
                    ease: 'power3.out',
                    scrollTrigger: { trigger: card, start: 'top 93%', once: true },
                });
            });

        gsap.from('.synkk-workflow-grid li', {
            y: 35,
            duration: 0.95,
            stagger: 0.16,
            ease: 'power3.out',
            scrollTrigger: { trigger: '.synkk-workflow-grid', start: 'top 85%', once: true },
        });

        gsap.from('.synkk-workflow-track span', {
            scaleX: 0,
            ease: 'none',
            scrollTrigger: { trigger: '.synkk-workflow', start: 'top 65%', end: 'bottom 65%', scrub: 0.6 },
        });

        gsap.to('.synkk-reading-progress span', {
            scaleX: 1,
            ease: 'none',
            scrollTrigger: { trigger: page, start: 'top top', end: 'bottom bottom', scrub: true },
        });

        if (desktop) {
            gsap.fromTo(
                '.synkk-product-viewer',
                { rotateX: 7, scale: 0.94 },
                {
                    rotateX: 0,
                    scale: 1,
                    ease: 'none',
                    scrollTrigger: { trigger: '.synkk-product', start: 'top 90%', end: 'top 10%', scrub: 0.7 },
                },
            );

            gsap.to('.synkk-laptop', {
                yPercent: -7,
                rotateZ: 0,
                ease: 'none',
                scrollTrigger: { trigger: '.synkk-hero', start: 'top top', end: 'bottom top', scrub: 0.8 },
            });

            gsap.to('.synkk-graph-peek', {
                yPercent: -20,
                rotate: -2,
                ease: 'none',
                scrollTrigger: { trigger: '.synkk-hero', start: 'top top', end: 'bottom top', scrub: 0.8 },
            });
        }
    });
    initializeMobileMenu(page.querySelector('[data-mobile-menu]'));
    initializeSurfaceShowcase(page.querySelector('[data-surface-showcase]'));

    page.querySelectorAll('.synkk-faq-list details').forEach((detail) => {
        detail.addEventListener('toggle', () => ScrollTrigger.refresh(true));
    });

    document.fonts.ready.then(() => ScrollTrigger.refresh());
    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}
