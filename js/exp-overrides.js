/**
 * OJG A/B engine — variant override applier.
 *
 * Reads the global `window.__VARIANT_OVERRIDES` map and patches the DOM
 * accordingly. The map is populated by exp-bootstrap (loaded earlier in the
 * head) from the `ojg_exp` cookie plus a small inline server-side hint.
 *
 * Override shapes:
 *   { "type": "text",   "selector": "...", "value": "..." }
 *   { "type": "html",   "selector": "...", "value": "..." }
 *   { "type": "attr",   "selector": "...", "name": "...", "value": "..." }
 *   { "type": "style",  "selector": "...", "value": { color: "red" } }
 *   { "type": "config", "key": "pricing.pcos-90", "value": { ... } }
 *
 * Strategy:
 *   - MutationObserver catches late-rendered nodes.
 *   - Falls back to DOMContentLoaded + a 5s safety retry.
 *   - Idempotent: each override is tagged with `data-exp-applied` so we don't
 *     re-apply on every mutation tick.
 */
(function () {
    'use strict';

    const FLAG = 'data-exp-applied';
    let applied = 0;

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

    function applyOne(ov) {
        if (!ov || !ov.type) return;
        let targets = [];
        try {
            if (ov.selector) {
                targets = ov.type === 'config' ? [document] : $$(ov.selector);
            }
        } catch (e) {
            console.warn('[exp-overrides] bad selector', ov.selector, e);
            return;
        }
        switch (ov.type) {
            case 'text':
                targets.forEach(t => { t.textContent = ov.value; });
                break;
            case 'html':
                targets.forEach(t => { t.innerHTML = ov.value; });
                break;
            case 'attr':
                targets.forEach(t => { t.setAttribute(ov.name, ov.value); });
                break;
            case 'style':
                targets.forEach(t => {
                    Object.assign(t.style, ov.value || {});
                });
                break;
            case 'config':
                window.OJG = window.OJG || {};
                window.OJG.configOverrides = window.OJG.configOverrides || {};
                window.OJG.configOverrides[ov.key] = ov.value;
                // Also patch DataManager if it's already loaded
                if (window.DataManager && ov.key.startsWith('pricing.')) {
                    const planId = ov.key.replace('pricing.', '');
                    if (window.DataManager.updatePricing) {
                        window.DataManager.updatePricing(planId, ov.value);
                    }
                }
                if (window.DataManager && ov.key.startsWith('testimonials.')) {
                    const cat = ov.key.replace('testimonials.', '');
                    const list = Array.isArray(ov.value) ? ov.value : [ov.value];
                    list.forEach(t => window.DataManager.addTestimonial && window.DataManager.addTestimonial(cat, t));
                }
                break;
            default:
                console.warn('[exp-overrides] unknown override type', ov.type);
        }
    }

    function applyOverrides(map) {
        if (!map) return;
        Object.keys(map).forEach(key => {
            const ov = map[key];
            // Mark this key as applied by tagging the first matched element
            try {
                if (ov.selector && ov.type !== 'config') {
                    const el = $(ov.selector);
                    if (el) el.setAttribute(FLAG, key);
                }
            } catch (e) { /* selector might be invalid for config type */ }
            applyOne(ov);
            applied++;
        });
    }

    function init() {
        const map = window.__VARIANT_OVERRIDES || {};
        applyOverrides(map);

        // Anti-flicker release: once we've applied, show the body.
        // The head script sets html{opacity:0} until this fires.
        if (document.documentElement) {
            document.documentElement.style.opacity = '';
            document.documentElement.classList.add('exp-ready');
        }

        // Watch for late-rendered content
        if (typeof MutationObserver !== 'undefined') {
            const mo = new MutationObserver(() => {
                applyOverrides(window.__VARIANT_OVERRIDES || {});
            });
            mo.observe(document.body || document.documentElement, {
                childList: true,
                subtree: true,
            });
        }

        // Hard 300ms timeout to release the opacity:0 even if we never
        // matched anything (e.g. no overrides for this variant).
        setTimeout(() => {
            if (document.documentElement) {
                document.documentElement.style.opacity = '';
                document.documentElement.classList.add('exp-ready');
            }
        }, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
