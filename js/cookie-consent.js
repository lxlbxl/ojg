/**
 * GDPR & NDPR Cookie Consent Banner
 * 
 * Features:
 * - Granular consent options (essential, analytics, marketing)
 * - Consent recording and management
 * - Withdrawal mechanism
 * - Compliance with GDPR and NDPR requirements
 */

(function () {
    'use strict';

    const CONSENT_VERSION = '1.0';
    const CONSENT_KEY = 'ojg_cookie_consent';
    const CONSENT_EXPIRY_DAYS = 365;

    // Default consent state
    const defaultConsent = {
        granted: false,
        timestamp: null,
        version: CONSENT_VERSION,
        categories: {
            essential: true,  // Always active
            analytics: false,
            marketing: false,
            functional: false
        }
    };

    // Get current consent
    function getConsent() {
        try {
            const stored = localStorage.getItem(CONSENT_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                // Check if consent has expired
                if (parsed.timestamp) {
                    const expiry = new Date(parsed.timestamp);
                    expiry.setDate(expiry.getDate() + CONSENT_EXPIRY_DAYS);
                    if (new Date() > expiry) {
                        // Consent expired, reset
                        localStorage.removeItem(CONSENT_KEY);
                        return defaultConsent;
                    }
                }
                return parsed;
            }
        } catch (e) {
            console.error('Error reading consent:', e);
        }
        return defaultConsent;
    }

    // Save consent
    function saveConsent(consent) {
        try {
            consent.timestamp = new Date().toISOString();
            consent.version = CONSENT_VERSION;
            localStorage.setItem(CONSENT_KEY, JSON.stringify(consent));

            // Update dataLayer for Google Tag Manager
            if (window.dataLayer) {
                window.dataLayer.push({
                    'event': 'consent_update',
                    'consent': consent
                });
            }

            // Trigger custom event for other scripts
            document.dispatchEvent(new CustomEvent('consentUpdate', { detail: consent }));
        } catch (e) {
            console.error('Error saving consent:', e);
        }
    }

    // Check if consent is granted for category
    function hasConsent(category) {
        const consent = getConsent();
        if (category === 'essential') return true;
        return consent.categories[category] || false;
    }

    // Record consent to backend for audit trail
    function recordConsentToBackend(consent) {
        // Get session ID or user identifier
        const sessionId = getSessionId();
        const email = getUserEmail();

        if (!sessionId && !email) return;

        fetch('/backend/api/record-consent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: getCsrfToken(),
                session_id: sessionId,
                email: email,
                consent: consent,
                url: window.location.href,
                user_agent: navigator.userAgent
            })
        }).catch(err => console.error('Consent recording failed:', err));
    }

    // Helper functions
    function getSessionId() {
        return localStorage.getItem('ojg_session_id') ||
            document.querySelector('[data-session-id]')?.dataset?.sessionId;
    }

    function getUserEmail() {
        return localStorage.getItem('ojg_user_email') || '';
    }

    function getCsrfToken() {
        return document.querySelector('[name="csrf_token"]')?.value ||
            localStorage.getItem('csrf_token') || '';
    }

    // Create consent banner HTML — slim, on-brand, GDPR & NDPR compliant
    function createBannerHTML() {
        return `
            <style>
                #ojg-cookie-bar { font-family: 'Outfit', system-ui, sans-serif; }
                #ojg-cookie-bar button { cursor: pointer; font-family: inherit; transition: opacity 0.15s; }
                #ojg-cookie-bar button:hover { opacity: 0.8; }
                #ojg-cookie-modal-overlay { font-family: 'Outfit', system-ui, sans-serif; }
                .ojg-toggle { position: relative; display: inline-block; width: 40px; height: 22px; }
                .ojg-toggle input { opacity: 0; width: 0; height: 0; }
                .ojg-toggle-slider {
                    position: absolute; inset: 0; background: #d1d5db; border-radius: 22px; transition: 0.25s;
                }
                .ojg-toggle-slider::before {
                    content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px;
                    background: white; border-radius: 50%; transition: 0.25s;
                }
                .ojg-toggle input:checked + .ojg-toggle-slider { background: #166534; }
                .ojg-toggle input:checked + .ojg-toggle-slider::before { transform: translateX(18px); }
            </style>

            <!-- Slim cookie bar -->
            <div id="ojg-cookie-bar" role="dialog" aria-label="Cookie consent" aria-live="polite"
                style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9998;
                       background:rgba(15,57,34,0.96);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
                       border-top:1px solid rgba(255,255,255,0.07);padding:11px 20px;">
                <div style="max-width:1152px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;">
                    <p style="color:rgba(244,241,234,0.7);font-size:11.5px;line-height:1.5;flex:1;min-width:200px;margin:0;">
                        🍪 We use essential &amp; analytics cookies.
                        <a href="/privacy-policy.html" target="_blank" rel="noopener"
                           style="color:rgba(244,241,234,0.85);text-decoration:underline;text-underline-offset:2px;">Privacy&nbsp;Policy</a>
                        &nbsp;·&nbsp;GDPR &amp; NDPR compliant.
                    </p>
                    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                        <button id="consent-manage"
                            style="color:rgba(244,241,234,0.55);font-size:11.5px;background:none;border:none;
                                   padding:0;text-decoration:underline;text-underline-offset:2px;">
                            Manage
                        </button>
                        <button id="consent-reject-all"
                            style="color:rgba(244,241,234,0.75);font-size:11.5px;background:none;
                                   border:1px solid rgba(244,241,234,0.22);padding:5px 13px;border-radius:999px;">
                            Essential only
                        </button>
                        <button id="consent-accept-all"
                            style="color:#0f3922;background:#F4F1EA;font-size:11.5px;font-weight:600;
                                   border:none;padding:6px 14px;border-radius:999px;">
                            Accept all
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cookie preferences modal -->
            <div id="ojg-cookie-modal-overlay"
                style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;
                       align-items:center;justify-content:center;padding:16px;">
                <div style="background:#FDFCF8;border-radius:20px;max-width:440px;width:100%;
                            max-height:90vh;overflow-y:auto;padding:28px;position:relative;">

                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                        <h2 style="font-size:17px;font-weight:700;color:#0f3922;margin:0;">Cookie Preferences</h2>
                        <button id="ojg-close-modal"
                            style="background:none;border:none;color:#9ca3af;font-size:20px;line-height:1;padding:0;">✕</button>
                    </div>

                    <p style="font-size:12px;color:#6b7280;line-height:1.6;margin-bottom:18px;">
                        We use cookies to improve your experience and analyse site usage, in line with
                        GDPR (EU) and NDPR (Nigeria) regulations. Adjust your preferences below.
                    </p>

                    <!-- Essential -->
                    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:10px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <div>
                                <p style="font-size:13px;font-weight:600;color:#0f3922;margin:0 0 2px;">Essential</p>
                                <p style="font-size:11px;color:#9ca3af;margin:0;">Login, sessions, security. Cannot be disabled.</p>
                            </div>
                            <span style="font-size:11px;font-weight:600;color:#166534;white-space:nowrap;">Always on</span>
                        </div>
                    </div>

                    <!-- Analytics -->
                    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:10px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <div>
                                <p style="font-size:13px;font-weight:600;color:#0f3922;margin:0 0 2px;">Analytics</p>
                                <p style="font-size:11px;color:#9ca3af;margin:0;">Anonymous usage data to help us improve the site.</p>
                            </div>
                            <label class="ojg-toggle" aria-label="Analytics cookies">
                                <input type="checkbox" id="modal-consent-analytics">
                                <span class="ojg-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Marketing -->
                    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <div>
                                <p style="font-size:13px;font-weight:600;color:#0f3922;margin:0 0 2px;">Marketing</p>
                                <p style="font-size:11px;color:#9ca3af;margin:0;">Personalised content and relevant offers.</p>
                            </div>
                            <label class="ojg-toggle" aria-label="Marketing cookies">
                                <input type="checkbox" id="modal-consent-marketing">
                                <span class="ojg-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <button id="save-preferences"
                        style="width:100%;background:#0f3922;color:#F4F1EA;font-size:13px;font-weight:600;
                               border:none;padding:12px;border-radius:12px;">
                        Save preferences
                    </button>

                    <p style="text-align:center;margin-top:14px;font-size:11px;color:#d1d5db;">
                        <a href="/privacy-policy.html" target="_blank" rel="noopener"
                           style="color:#6b7280;text-decoration:underline;text-underline-offset:2px;">
                            Full Privacy Policy
                        </a>
                        &nbsp;·&nbsp; You can change preferences any time.
                    </p>
                </div>
            </div>

            <!-- Hidden inputs kept for syncToggles compatibility -->
            <input type="checkbox" id="consent-analytics" style="display:none">
            <input type="checkbox" id="consent-marketing" style="display:none">
        `;
    }

    // Initialize banner
    function init() {
        // Add banner to page
        const bannerContainer = document.createElement('div');
        bannerContainer.innerHTML = createBannerHTML();
        document.body.appendChild(bannerContainer);

        const consent = getConsent();
        const banner = document.getElementById('ojg-cookie-bar');
        const modal = document.getElementById('ojg-cookie-modal-overlay');

        // Don't show if consent already recorded in localStorage
        if (consent.granted) {
            syncToggles(consent.categories);
            return;
        }

        // Don't show again if already shown during this browser session
        // (user saw it on a previous page this visit but hasn't chosen yet)
        if (sessionStorage.getItem('ojg_banner_seen')) {
            return;
        }

        // Show banner and mark as seen for this session
        banner.style.display = 'block';
        sessionStorage.setItem('ojg_banner_seen', '1');

        // Accept all
        document.getElementById('consent-accept-all').addEventListener('click', function () {
            const newConsent = {
                granted: true,
                categories: { essential: true, analytics: true, marketing: true, functional: true }
            };
            saveConsent(newConsent);
            recordConsentToBackend(newConsent);
            banner.style.display = 'none';
            syncToggles(newConsent.categories);
        });

        // Essential only
        document.getElementById('consent-reject-all').addEventListener('click', function () {
            const newConsent = {
                granted: true,
                categories: { essential: true, analytics: false, marketing: false, functional: false }
            };
            saveConsent(newConsent);
            recordConsentToBackend(newConsent);
            banner.style.display = 'none';
            syncToggles(newConsent.categories);
        });

        // Open modal
        document.getElementById('consent-manage').addEventListener('click', function () {
            modal.style.display = 'flex';
        });

        // Close modal
        document.getElementById('ojg-close-modal').addEventListener('click', function () {
            modal.style.display = 'none';
        });

        // Save from modal
        document.getElementById('save-preferences').addEventListener('click', function () {
            const newConsent = {
                granted: true,
                categories: {
                    essential: true,
                    analytics: document.getElementById('modal-consent-analytics').checked,
                    marketing: document.getElementById('modal-consent-marketing').checked,
                    functional: false
                }
            };
            saveConsent(newConsent);
            recordConsentToBackend(newConsent);
            modal.style.display = 'none';
            banner.style.display = 'none';
            syncToggles(newConsent.categories);
        });

        // Close modal on backdrop click
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }

    // Sync toggle states
    function syncToggles(categories) {
        const bannerAnalytics = document.getElementById('consent-analytics');
        const bannerMarketing = document.getElementById('consent-marketing');
        const modalAnalytics = document.getElementById('modal-consent-analytics');
        const modalMarketing = document.getElementById('modal-consent-marketing');

        if (bannerAnalytics) bannerAnalytics.checked = categories.analytics;
        if (bannerMarketing) bannerMarketing.checked = categories.marketing;
        if (modalAnalytics) modalAnalytics.checked = categories.analytics;
        if (modalMarketing) modalMarketing.checked = categories.marketing;
    }

    // Expose API
    window.OJGConsent = {
        get: getConsent,
        has: hasConsent,
        save: saveConsent,
        init: init
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();