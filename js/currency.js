/**
 * OJG Herbal Multi-Currency Utility
 * Supports all currencies available on Flutterwave
 * Used across ojg-usd, ojg-ngn, and admin panels
 */

const OJGCurrency = (function () {

    // ─────────────────────────────────────────────
    // All currencies supported by Flutterwave
    // ─────────────────────────────────────────────
    const SUPPORTED_CURRENCIES = {
        NGN: { code: 'NGN', symbol: '₦', name: 'Nigerian Naira',        locale: 'en-NG', decimals: 0 },
        USD: { code: 'USD', symbol: '$', name: 'US Dollar',              locale: 'en-US', decimals: 2 },
        GBP: { code: 'GBP', symbol: '£', name: 'British Pound',          locale: 'en-GB', decimals: 2 },
        EUR: { code: 'EUR', symbol: '€', name: 'Euro',                   locale: 'en-EU', decimals: 2 },
        GHS: { code: 'GHS', symbol: 'GH₵', name: 'Ghanaian Cedi',       locale: 'en-GH', decimals: 2 },
        KES: { code: 'KES', symbol: 'KSh', name: 'Kenyan Shilling',      locale: 'en-KE', decimals: 0 },
        ZAR: { code: 'ZAR', symbol: 'R',  name: 'South African Rand',    locale: 'en-ZA', decimals: 2 },
        TZS: { code: 'TZS', symbol: 'TSh', name: 'Tanzanian Shilling',   locale: 'sw-TZ', decimals: 0 },
        UGX: { code: 'UGX', symbol: 'USh', name: 'Ugandan Shilling',     locale: 'en-UG', decimals: 0 },
        RWF: { code: 'RWF', symbol: 'RF',  name: 'Rwandan Franc',        locale: 'en-RW', decimals: 0 },
        ZMW: { code: 'ZMW', symbol: 'ZK',  name: 'Zambian Kwacha',       locale: 'en-ZM', decimals: 2 },
        XAF: { code: 'XAF', symbol: 'FCFA', name: 'Central African Franc',locale: 'fr-CM', decimals: 0 },
        XOF: { code: 'XOF', symbol: 'CFA', name: 'West African Franc',   locale: 'fr-SN', decimals: 0 },
        EGP: { code: 'EGP', symbol: 'E£',  name: 'Egyptian Pound',       locale: 'ar-EG', decimals: 2 },
        MAD: { code: 'MAD', symbol: 'MAD', name: 'Moroccan Dirham',      locale: 'fr-MA', decimals: 2 },
        ETB: { code: 'ETB', symbol: 'Br',  name: 'Ethiopian Birr',       locale: 'am-ET', decimals: 2 },
        MWK: { code: 'MWK', symbol: 'MK',  name: 'Malawian Kwacha',      locale: 'en-MW', decimals: 2 },
        SLL: { code: 'SLL', symbol: 'Le',  name: 'Sierra Leonean Leone',  locale: 'en-SL', decimals: 0 },
        CAD: { code: 'CAD', symbol: 'CA$', name: 'Canadian Dollar',       locale: 'en-CA', decimals: 2 },
        AUD: { code: 'AUD', symbol: 'A$',  name: 'Australian Dollar',     locale: 'en-AU', decimals: 2 },
    };

    // ─────────────────────────────────────────────
    // Country → Currency mapping (ISO 3166-1 alpha-2)
    // ─────────────────────────────────────────────
    const COUNTRY_TO_CURRENCY = {
        // Africa
        NG: 'NGN', GH: 'GHS', KE: 'KES', ZA: 'ZAR', TZ: 'TZS',
        UG: 'UGX', RW: 'RWF', ZM: 'ZMW', ET: 'ETB', EG: 'EGP',
        MA: 'MAD', SL: 'SLL', MW: 'MWK',
        CM: 'XAF', CF: 'XAF', TD: 'XAF', CG: 'XAF', GQ: 'XAF', GA: 'XAF',
        BJ: 'XOF', BF: 'XOF', CI: 'XOF', GW: 'XOF', ML: 'XOF',
        NE: 'XOF', SN: 'XOF', TG: 'XOF',
        // Europe
        GB: 'GBP',
        DE: 'EUR', FR: 'EUR', IT: 'EUR', ES: 'EUR', NL: 'EUR', BE: 'EUR',
        AT: 'EUR', PT: 'EUR', IE: 'EUR', FI: 'EUR', GR: 'EUR',
        // Americas
        US: 'USD', CA: 'CAD',
        // Oceania
        AU: 'AUD', NZ: 'AUD',
        // Default for all others
        DEFAULT: 'USD',
    };

    // Flutterwave payment method options per currency
    const PAYMENT_METHODS = {
        NGN: 'card,banktransfer,ussd,mobilemoney',
        GHS: 'card,mobilemoney',
        KES: 'card,mobilemoney',
        ZAR: 'card',
        USD: 'card',
        GBP: 'card',
        EUR: 'card',
        DEFAULT: 'card',
    };

    // ─────────────────────────────────────────────
    // Core Formatting
    // ─────────────────────────────────────────────

    /**
     * Format an amount with the correct currency symbol and locale.
     * @param {number} amount
     * @param {string} currencyCode - e.g. 'NGN', 'USD'
     * @returns {string}
     */
    function formatAmount(amount, currencyCode) {
        const cur = SUPPORTED_CURRENCIES[currencyCode] || SUPPORTED_CURRENCIES['USD'];
        try {
            return new Intl.NumberFormat(cur.locale, {
                style: 'currency',
                currency: cur.code,
                minimumFractionDigits: cur.decimals,
                maximumFractionDigits: cur.decimals,
            }).format(amount);
        } catch (e) {
            // Fallback for environments without full Intl support
            return cur.symbol + Number(amount).toFixed(cur.decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }

    /**
     * Get currency symbol only.
     * @param {string} currencyCode
     * @returns {string}
     */
    function getSymbol(currencyCode) {
        return (SUPPORTED_CURRENCIES[currencyCode] || SUPPORTED_CURRENCIES['USD']).symbol;
    }

    /**
     * Get full currency info object.
     * @param {string} currencyCode
     * @returns {Object}
     */
    function getCurrencyInfo(currencyCode) {
        return SUPPORTED_CURRENCIES[currencyCode] || SUPPORTED_CURRENCIES['USD'];
    }

    /**
     * Get currency code for a given country code.
     * @param {string} countryCode - ISO 2-letter code e.g. 'NG', 'US'
     * @returns {string} currency code
     */
    function getCurrencyForCountry(countryCode) {
        return COUNTRY_TO_CURRENCY[countryCode] || COUNTRY_TO_CURRENCY['DEFAULT'];
    }

    /**
     * Get preferred Flutterwave payment methods for a currency.
     * @param {string} currencyCode
     * @returns {string}
     */
    function getPaymentMethods(currencyCode) {
        return PAYMENT_METHODS[currencyCode] || PAYMENT_METHODS['DEFAULT'];
    }

    /**
     * Build a <select> options HTML string of all supported currencies.
     * @param {string} selectedCode - currently selected currency
     * @returns {string} HTML options
     */
    function buildCurrencyOptions(selectedCode) {
        return Object.entries(SUPPORTED_CURRENCIES).map(([code, info]) => {
            const selected = code === selectedCode ? ' selected' : '';
            return `<option value="${code}"${selected}>${code} — ${info.name} (${info.symbol})</option>`;
        }).join('\n');
    }

    /**
     * Get all supported currency codes.
     * @returns {string[]}
     */
    function getSupportedCodes() {
        return Object.keys(SUPPORTED_CURRENCIES);
    }

    // ─────────────────────────────────────────────
    // IP / Geolocation Detection (for ojg-usd)
    // ─────────────────────────────────────────────

    let _detectedCountry = null;
    let _detectedCurrency = null;
    let _detectionPromise = null;

    /**
     * Detect visitor's country and corresponding currency via IP.
     * Tries multiple free APIs with fallback. Caches result.
     * @returns {Promise<{country: string, currency: string}>}
     */
    async function detectLocationCurrency() {
        // Return cached result
        if (_detectedCountry) {
            return { country: _detectedCountry, currency: _detectedCurrency };
        }

        // Return in-flight promise to avoid duplicate requests
        if (_detectionPromise) {
            return _detectionPromise;
        }

        _detectionPromise = (async () => {
            // Check sessionStorage cache first
            try {
                const cached = sessionStorage.getItem('ojg_geo_currency');
                if (cached) {
                    const parsed = JSON.parse(cached);
                    _detectedCountry = parsed.country;
                    _detectedCurrency = parsed.currency;
                    return parsed;
                }
            } catch (e) { /* ignore */ }

            // Try server-side endpoint first (avoids CORS)
            const apis = [
                () => fetchFromOwnBackend(),
                () => fetchFromIpApi(),
                () => fetchFromIpInfo(),
            ];

            for (const apiFn of apis) {
                try {
                    const result = await apiFn();
                    if (result && result.country) {
                        _detectedCountry = result.country;
                        _detectedCurrency = getCurrencyForCountry(result.country);
                        const output = { country: _detectedCountry, currency: _detectedCurrency };
                        // Cache in sessionStorage for this browser session
                        try { sessionStorage.setItem('ojg_geo_currency', JSON.stringify(output)); } catch (e) { /* ignore */ }
                        return output;
                    }
                } catch (e) {
                    console.warn('Geo API failed, trying next:', e.message);
                }
            }

            // Final fallback: default to USD
            _detectedCountry = 'US';
            _detectedCurrency = 'USD';
            return { country: 'US', currency: 'USD' };
        })();

        return _detectionPromise;
    }

    async function fetchFromOwnBackend() {
        // Try to detect relative path to backend
        const possiblePaths = [
            '/backend/api/detect-currency.php',
            '../../backend/api/detect-currency.php',
            '../backend/api/detect-currency.php',
        ];
        for (const path of possiblePaths) {
            try {
                const resp = await fetch(path, { signal: AbortSignal.timeout(3000) });
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.country) return data;
                }
            } catch (e) { /* try next */ }
        }
        throw new Error('Own backend not reachable');
    }

    async function fetchFromIpApi() {
        const resp = await fetch('https://ip-api.com/json/?fields=countryCode', {
            signal: AbortSignal.timeout(4000)
        });
        const data = await resp.json();
        return { country: data.countryCode };
    }

    async function fetchFromIpInfo() {
        const resp = await fetch('https://ipapi.co/json/', {
            signal: AbortSignal.timeout(4000)
        });
        const data = await resp.json();
        return { country: data.country_code };
    }

    // ─────────────────────────────────────────────
    // Revenue Aggregation Helpers (for admin)
    // ─────────────────────────────────────────────

    /**
     * Group an array of sale objects by currency, summing revenue.
     * Each sale must have `amount` and optionally `currency`.
     * @param {Array} sales
     * @param {string} defaultCurrency
     * @returns {Object} { NGN: { total, count }, USD: { total, count }, ... }
     */
    function groupByCurrency(sales, defaultCurrency = 'NGN') {
        const result = {};
        for (const sale of sales) {
            const status = sale.payment_status || sale.status || 'pending';
            if (status !== 'completed' && status !== 'successful') continue;
            const cur = (sale.currency || defaultCurrency).toUpperCase();
            const amount = parseFloat(sale.amount || 0);
            if (!result[cur]) result[cur] = { total: 0, count: 0, currency: getCurrencyInfo(cur) };
            result[cur].total += amount;
            result[cur].count++;
        }
        return result;
    }

    /**
     * Format revenue breakdown as a human-readable summary.
     * @param {Object} grouped - result from groupByCurrency()
     * @returns {string}
     */
    function formatRevenueSummary(grouped) {
        return Object.entries(grouped)
            .map(([code, data]) => `${formatAmount(data.total, code)} (${data.count} orders)`)
            .join(' · ');
    }

    // ─────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────
    return {
        SUPPORTED_CURRENCIES,
        COUNTRY_TO_CURRENCY,
        formatAmount,
        getSymbol,
        getCurrencyInfo,
        getCurrencyForCountry,
        getPaymentMethods,
        buildCurrencyOptions,
        getSupportedCodes,
        detectLocationCurrency,
        groupByCurrency,
        formatRevenueSummary,
    };

})();

// Make available globally
if (typeof window !== 'undefined') {
    window.OJGCurrency = OJGCurrency;
}

// Node.js export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OJGCurrency;
}

console.log('💱 OJG Currency Utility loaded — ' + Object.keys(OJGCurrency.SUPPORTED_CURRENCIES).length + ' currencies supported');
