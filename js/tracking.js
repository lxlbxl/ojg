/**
 * Centralized Tracker for PCOS Funnel
 * 
 * This script is responsible for injecting tracking codes (GTM, Pixels, etc.)
 * into the <head> and <body> of the page.
 * 
 * Usage:
 * Simply include this script in the <head> of your HTML file:
 * <script src="../js/tracking.js"></script>
 */

(function () {
    'use strict';

    // --- CONFIGURATION ---
    const CONFIG = {
        GTM_ID: 'GTM-5Q5HPMZ2' // Google Tag Manager ID
    };

    /**
     * Helper to create and append script tags
     */
    function injectScript(content, location = 'head', isExternal = false, src = '') {
        const script = document.createElement('script');
        if (isExternal) {
            script.src = src;
            script.async = true;
        } else {
            script.innerHTML = content;
        }

        if (location === 'head') {
            document.head.appendChild(script);
        } else {
            document.body.appendChild(script);
        }
    }

    /**
     * Helper to create and append HTML strings (for noscript etc)
     */
    function injectHTML(htmlString, location = 'body') {
        const div = document.createElement('div');
        div.innerHTML = htmlString;

        // We use the first child because we want the actual element, not the wrapper div
        // usually. But for noscript/iframe it's tricky since they don't render inside div well if script disabled.
        // However, since we are Running JS, noscript tags strictly won't trigger for purely JS disabled users.
        // But we inject them for completeness or if they contain iframes that might load.
        // Note: Dynamically inserting <noscript> via JS is mostly redundant because if JS is off, this file won't run.
        // But for GTM, the <iframe> inside noscript is sometimes used for verification or other non-js tracking quirks.

        while (div.firstChild) {
            if (location === 'head') {
                document.head.appendChild(div.firstChild);
            } else {
                // Prepend to body to be "immediately after opening body tag" as requested
                document.body.insertBefore(div.firstChild, document.body.firstChild);
            }
        }
    }


    // --- IMPLEMENTATIONS ---

    // 1. Google Tag Manager (HEAD)
    const gtmHeadScript = `
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','${CONFIG.GTM_ID}');
    `;
    // We execute this directly or inject it? GTM code is self-executing.
    // However, since we are INSIDE a script, we can just run the function logic or inject a new script tag.
    // Injecting a new script tag is safer to ensure it runs in global scope exactly as GTM expects.
    injectScript(gtmHeadScript, 'head');


    // 2. Google Tag Manager (BODY - NOSCRIPT)
    // Note: As mentioned, this won't run if JS is disabled (catch-22), but we add it for DOM completeness.
    const gtmBodyContent = `
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=${CONFIG.GTM_ID}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    `;
    // Wait for body to be ready incase this script is in head
    document.addEventListener('DOMContentLoaded', function () {
        injectHTML(gtmBodyContent, 'body');
    });

    // --- FUTURE TRACKING CODES ---

    // Facebook Pixel
    const fbPixelCode = `
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '2722720648064540');
    fbq('track', 'PageView');
    `;
    injectScript(fbPixelCode, 'head');

    const fbNoScript = `
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=2722720648064540&ev=PageView&noscript=1"
    /></noscript>
    `;
    document.addEventListener('DOMContentLoaded', function () {
        injectHTML(fbNoScript, 'body');
    });

    // --- META PIXEL CONVERSION HELPER ---
    // Expose global helper for pages to fire conversion events
    window.OJGTracking = {
        /**
         * Fire a Meta Pixel standard event
         * @param {string} eventName - e.g. 'Purchase', 'Lead', 'InitiateCheckout', 'ViewContent'
         * @param {object} params - event parameters
         */
        trackEvent: function (eventName, params) {
            try {
                if (typeof fbq === 'function') {
                    fbq('track', eventName, params || {});
                    console.log('[OJGTracking] Meta Pixel event:', eventName, params);
                }
            } catch (e) {
                console.warn('[OJGTracking] Pixel error:', e);
            }
        },

        /** Track Purchase conversion */
        trackPurchase: function (amount, currency, planName) {
            this.trackEvent('Purchase', {
                value: parseFloat(amount) || 0,
                currency: currency || 'NGN',
                content_name: planName || 'PCOS Plan',
                content_type: 'product',
                content_category: 'health_plan'
            });
        },

        /** Track Lead (assessment completion) */
        trackLead: function (assessmentType, pcosType) {
            this.trackEvent('Lead', {
                content_name: (assessmentType || 'PCOS') + ' Assessment',
                content_category: assessmentType || 'pcos',
                content_type: pcosType || 'general'
            });
        },

        /** Track when user views a sales/product page */
        trackViewContent: function (planName, price, currency) {
            this.trackEvent('ViewContent', {
                content_name: planName || 'PCOS Plan',
                content_type: 'product',
                value: parseFloat(price) || 0,
                currency: currency || 'NGN'
            });
        },

        /** Track when user clicks "Buy Now" / starts checkout */
        trackInitiateCheckout: function (planName, price, currency) {
            this.trackEvent('InitiateCheckout', {
                content_name: planName || 'PCOS Plan',
                value: parseFloat(price) || 0,
                currency: currency || 'NGN',
                num_items: 1
            });
        },

        /** Track assessment form completion (registration) */
        trackCompleteRegistration: function (type) {
            this.trackEvent('CompleteRegistration', {
                content_name: (type || 'PCOS') + ' Assessment',
                status: 'completed'
            });
        },

        /**
         * Cookie helpers (shared with server-side router for ojg_sid)
         */
        getCookie: function (name) {
            const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : null;
        },

        setCookie: function (name, value, days) {
            const expires = new Date(Date.now() + (days || 365) * 86400000).toUTCString();
            document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
        },

        newSessionId: function () {
            return 'sess_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        },

        /**
         * Send a structured event to the experiment tracker endpoint.
         * Mirrors to funnel_tracking and (if a session has assignments) to experiment_events.
         * @param {string} eventName - one of: view, assessment_start, assessment_complete, results_view, plan_select, checkout_init, purchase
         * @param {object} params - { funnel, value, currency, metadata, transaction_id, product }
         */
        track: function (eventName, params) {
            try {
                params = params || {};
                // Resolve session id (set by router.php on first hit; fall back to a local one)
                let sessionId = this.getCookie('ojg_sid');
                if (!sessionId) {
                    sessionId = this.newSessionId();
                    this.setCookie('ojg_sid', sessionId, 365);
                }

                // Resolve experiment assignments from ojg_exp cookie
                const expRaw = this.getCookie('ojg_exp');
                let experimentId = null, variantId = null;
                if (expRaw) {
                    try {
                        const map = JSON.parse(expRaw);
                        // The PHP server resolves the active experiment for the funnel,
                        // but for client-side events we send the full map and let the server pick.
                        // We still set the most recent single one as the primary context.
                        const keys = Object.keys(map || {});
                        if (keys.length > 0) {
                            const last = keys[keys.length - 1];
                            experimentId = last;
                            variantId = (map[last] && (map[last].variant_id || map[last])) || null;
                            if (typeof variantId === 'object') variantId = variantId.variant_id || null;
                        }
                    } catch (e) { /* ignore */ }
                }

                // Resolve funnel: prefer explicit, then window.OJG.funnel, then path-based heuristic
                let funnel = params.funnel || (window.OJG && window.OJG.funnel) || null;
                if (!funnel) {
                    const p = window.location.pathname.toLowerCase();
                    const m = p.match(/^\/?(pcos|acne|weight|mens|egbon)(?:__[a-z0-9_-]+)?\//);
                    if (m) funnel = m[1];
                }

                const payload = {
                    session_id: sessionId,
                    funnel: funnel,
                    event: eventName,
                    experiment_id: experimentId,
                    variant_id: variantId,
                    value: params.value,
                    currency: params.currency,
                    transaction_id: params.transaction_id,
                    product: params.product,
                    metadata: params.metadata || {}
                };

                // Don't send empty optional fields
                Object.keys(payload).forEach(k => { if (payload[k] === undefined || payload[k] === null || payload[k] === '') delete payload[k]; });

                const url = (window.OJG && window.OJG.apiBase ? window.OJG.apiBase : '/backend/api') + '/track-event.php';
                const body = JSON.stringify(payload);
                if (navigator.sendBeacon) {
                    const blob = new Blob([body], { type: 'application/json' });
                    navigator.sendBeacon(url, blob);
                } else {
                    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true }).catch(function () { });
                }
            } catch (e) {
                console.warn('[OJGTracking] track error:', e);
            }
        }
    };

    // Convenience global
    window.OJG = window.OJG || {};
    window.OJG.track = function (eventName, params) { window.OJGTracking.track(eventName, params); };

})();
