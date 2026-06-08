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

    // Create consent banner HTML
    function createBannerHTML() {
        return `
            <div id="cookie-consent-banner" class="fixed bottom-0 left-0 right-0 bg-white shadow-2xl border-t z-50" style="display: none;">
                <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Main Message -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                🍪 We Value Your Privacy
                            </h3>
                            <p class="text-gray-600 text-sm mb-4">
                                We use cookies to enhance your browsing experience, analyze site traffic, 
                                and personalize content. By continuing to use our site, you consent to our 
                                use of cookies in accordance with our 
                                <a href="/privacy-policy.html" target="_blank" class="text-green-600 hover:underline">Privacy Policy</a>.
                            </p>
                            <p class="text-xs text-gray-500">
                                Compliant with GDPR (EU) and NDPR (Nigeria) regulations.
                            </p>
                        </div>
                        
                        <!-- Consent Options -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">Essential Cookies</p>
                                    <p class="text-xs text-gray-500">Required for site functionality</p>
                                </div>
                                <span class="text-green-600 text-sm font-medium">Always Active</span>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">Analytics Cookies</p>
                                    <p class="text-xs text-gray-500">Help us improve our site</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="consent-analytics" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-300 peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">Marketing Cookies</p>
                                    <p class="text-xs text-gray-500">Personalized ads and content</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="consent-marketing" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-300 peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t">
                        <button id="consent-reject-all" class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                            Reject Non-Essential
                        </button>
                        <button id="consent-customize" class="flex-1 px-6 py-3 border border-green-600 rounded-lg text-green-600 hover:bg-green-50 transition font-medium">
                            Customize Settings
                        </button>
                        <button id="consent-accept-all" class="flex-1 sm:flex-none px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            Accept All Cookies
                        </button>
                    </div>
                    
                    <!-- Manage Link -->
                    <div class="text-center mt-4">
                        <button id="consent-manage" class="text-sm text-gray-500 hover:text-gray-700 underline">
                            Manage Cookie Preferences
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Preferences Modal -->
            <div id="cookie-preferences-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" style="display: none;">
                <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Cookie Preferences</h2>
                            <button id="close-preferences" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="space-y-4 mb-6">
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Essential Cookies</h3>
                                        <p class="text-sm text-gray-600 mt-1">These cookies are necessary for the website to function properly. They enable basic functions like page navigation and access to secure areas.</p>
                                    </div>
                                    <span class="text-green-600 text-sm font-medium">Always Active</span>
                                </div>
                            </div>
                            
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Analytics Cookies</h3>
                                        <p class="text-sm text-gray-600 mt-1">These cookies help us understand how visitors use our website by collecting and reporting information anonymously. This helps us improve our website.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="modal-consent-analytics" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Marketing Cookies</h3>
                                        <p class="text-sm text-gray-600 mt-1">These cookies are used to track visitors across websites to display relevant ads and limit the number of times you see an advertisement.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="modal-consent-marketing" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex gap-3">
                            <button id="save-preferences" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                Save Preferences
                            </button>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t text-center">
                            <a href="/privacy-policy.html" target="_blank" class="text-sm text-green-600 hover:underline">
                                Learn more in our Privacy Policy
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Initialize banner
    function init() {
        // Add banner to page
        const bannerContainer = document.createElement('div');
        bannerContainer.innerHTML = createBannerHTML();
        document.body.appendChild(bannerContainer);

        const consent = getConsent();
        const banner = document.getElementById('cookie-consent-banner');
        const modal = document.getElementById('cookie-preferences-modal');

        // Check if consent already given
        if (consent.granted) {
            // Sync toggles with saved consent
            syncToggles(consent.categories);
            return; // Don't show banner
        }

        // Show banner
        banner.style.display = 'block';

        // Button handlers
        document.getElementById('consent-accept-all').addEventListener('click', function () {
            const newConsent = {
                granted: true,
                categories: {
                    essential: true,
                    analytics: true,
                    marketing: true,
                    functional: true
                }
            };
            saveConsent(newConsent);
            recordConsentToBackend(newConsent);
            banner.style.display = 'none';
            syncToggles(newConsent.categories);
        });

        document.getElementById('consent-reject-all').addEventListener('click', function () {
            const newConsent = {
                granted: true,
                categories: {
                    essential: true,
                    analytics: false,
                    marketing: false,
                    functional: false
                }
            };
            saveConsent(newConsent);
            recordConsentToBackend(newConsent);
            banner.style.display = 'none';
            syncToggles(newConsent.categories);
        });

        document.getElementById('consent-customize').addEventListener('click', function () {
            modal.style.display = 'flex';
        });

        document.getElementById('consent-manage').addEventListener('click', function () {
            modal.style.display = 'flex';
        });

        document.getElementById('close-preferences').addEventListener('click', function () {
            modal.style.display = 'none';
        });

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

        // Close modal on outside click
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
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