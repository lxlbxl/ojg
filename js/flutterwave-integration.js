/**
 * Flutterwave Integration for Nigerian Market
 * Handles payment processing with Naira currency
 */

class FlutterwaveIntegration {
    constructor() {
        this.config = {
            publicKey: null,
            currency: 'NGN',
            country: 'NG',
            loaded: false,
            scriptLoaded: false
        };

        this.pricingData = null;
        this.loadFlutterwaveScript();
        this.fetchPricing();
    }

    /**
     * Fetch pricing from backend
     */
    async fetchPricing() {
        try {
            // Use WebhookManager's local URL if available, otherwise guess
            let apiUrl = '/backend/api/get-pricing.php';

            if (window.WebhookManager && window.WebhookManager.config.localBaseUrl) {
                apiUrl = `${window.WebhookManager.config.localBaseUrl}/get-pricing.php`;
            } else {
                // Fallback logic
                const path = window.location.pathname;
                if (path.includes('/pcos-funnel/') || path.includes('/acne/') || path.includes('/weight/') || path.includes('/egbon-funnel/')) {
                    apiUrl = '../backend/api/get-pricing.php';
                }
            }

            const response = await fetch(apiUrl);
            const data = await response.json();

            if (data.success && data.data) {
                this.pricingData = data.data;

                // Set public key if available
                if (this.pricingData.config && this.pricingData.config.flutterwavePublicKey) {
                    this.config.publicKey = this.pricingData.config.flutterwavePublicKey;
                    console.log('✅ Flutterwave public key loaded from backend');
                }

                console.log('✅ Pricing loaded from backend');
                
                // If geo detection is active on this page, let's wait for it or resolve currency
                if (window.OJGCurrency) {
                    try {
                        const geo = await window.OJGCurrency.detectLocationCurrency();
                        console.log(`🌍 Geolocation detected: ${geo.country} -> ${geo.currency}`);
                        
                        // Set active visitor currency/country for defaults
                        this.config.currency = geo.currency;
                        this.config.country = geo.country;
                    } catch (geoErr) {
                        console.warn('⚠️ Geolocation detection failed, using defaults:', geoErr);
                    }
                }

                this.updateUIWithPrices();
            }
        } catch (error) {
            console.warn('⚠️ Failed to fetch pricing, using defaults:', error);
        }
    }

    /**
     * Get current funnel identifier
     */
    getCurrentFunnel() {
        const path = window.location.pathname;
        if (path.includes('/egbon-funnel/') || path.includes('/egbon/')) return 'egbon';
        if (path.includes('/pcos-funnel/') || path.includes('/pcos/')) return 'pcos';
        if (path.includes('/acne/')) return 'acne';
        if (path.includes('/weight/')) return 'weight';
        return 'pcos'; // Default
    }

    /**
     * Update UI elements with dynamic prices
     */
    updateUIWithPrices() {
        const plans = this.getPaymentPlans();
        if (!plans) return;

        // Update elements with data-plan-price attribute
        document.querySelectorAll('[data-plan-price]').forEach(el => {
            const planType = el.getAttribute('data-plan-price');
            if (plans[planType]) {
                const plan = plans[planType];
                el.textContent = this.formatAmount(plan.price, plan.currency || this.config.currency);
            }
        });

        // Update elements with data-plan-name attribute
        document.querySelectorAll('[data-plan-name]').forEach(el => {
            const planType = el.getAttribute('data-plan-name');
            if (plans[planType]) {
                el.textContent = plans[planType].name;
            }
        });
    }

    /**
     * Get payment plans configuration
     */
    getPaymentPlans() {
        const funnel = this.getCurrentFunnel();

        if (this.pricingData && this.pricingData.plans && this.pricingData.plans[funnel]) {
            return this.pricingData.plans[funnel];
        }

        // Fallback defaults (only for PCOS as legacy fallback)
        if (funnel === 'pcos') {
            return {
                '90-day': {
                    name: '90-Day PCOS Complete Plan',
                    price: 45000,
                    currency: 'NGN',
                    description: 'Complete 90-day PCOS management program',
                    features: []
                },
                '30-day': {
                    name: '30-Day PCOS Starter Plan',
                    price: 18000,
                    currency: 'NGN',
                    description: 'Essential 30-day PCOS starter program',
                    features: []
                }
            };
        }

        return {};
    }

    /**
     * Get formatted price for display
     */
    getFormattedPrice(planType) {
        const plans = this.getPaymentPlans();
        const plan = plans[planType];
        return plan ? this.formatAmount(plan.price, plan.currency || this.config.currency) : null;
    }

    /**
     * Load Flutterwave script dynamically
     */
    async loadFlutterwaveScript() {
        if (this.config.scriptLoaded) return;

        try {
            const script = document.createElement('script');
            script.src = 'https://checkout.flutterwave.com/v3.js';
            script.async = true;

            script.onload = () => {
                this.config.scriptLoaded = true;
                console.log('✅ Flutterwave script loaded successfully');
            };

            script.onerror = () => {
                console.error('❌ Failed to load Flutterwave script');
            };

            document.head.appendChild(script);
        } catch (error) {
            console.error('❌ Error loading Flutterwave script:', error);
        }
    }

    /**
     * Initialize with public key from webhook manager or backend
     */
    async initialize() {
        // If we already have the key from backend, use it
        if (this.config.publicKey) {
            this.config.loaded = true;
            return true;
        }

        try {
            if (!window.WebhookManager) {
                // If no WebhookManager and no backend key, we can't proceed
                throw new Error('WebhookManager not available and no backend key found');
            }

            const paymentConfig = await window.WebhookManager.getFlutterwaveConfig();

            if (paymentConfig && paymentConfig.success && paymentConfig.data) {
                this.config.publicKey = paymentConfig.data.flutterwavePublicKey;
                this.config.loaded = true;
                console.log('✅ Flutterwave initialized with public key from WebhookManager');
                return true;
            } else {
                throw new Error('Failed to get Flutterwave configuration');
            }
        } catch (error) {
            console.error('❌ Flutterwave initialization failed:', error);
            return false;
        }
    }

    /**
     * Format amount using OJGCurrency if loaded, else fallback
     */
    formatAmount(amount, currencyCode = null) {
        const cur = currencyCode || this.config.currency || 'NGN';
        if (window.OJGCurrency) {
            return window.OJGCurrency.formatAmount(amount, cur);
        }
        
        // Simple client-side fallback
        const symbol = cur === 'NGN' ? '₦' : (cur === 'USD' ? '$' : cur + ' ');
        return symbol + new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount);
    }

    /**
     * Process payment for plans
     */
    async processPayment(paymentData) {
        try {
            if (!this.config.loaded) {
                const initialized = await this.initialize();
                if (!initialized) {
                    throw new Error('Flutterwave not initialized');
                }
            }

            if (!this.config.scriptLoaded) {
                throw new Error('Flutterwave script not loaded');
            }

            // OVERRIDE PRICE AND CURRENCY FROM DB IF AVAILABLE
            const plans = this.getPaymentPlans();
            let paymentCurrency = this.config.currency;
            
            if (plans && plans[paymentData.plan]) {
                const dbPrice = plans[paymentData.plan].price;
                const dbCurrency = plans[paymentData.plan].currency || 'NGN';
                
                if (dbPrice && dbPrice != paymentData.amount) {
                    console.log(`💰 Overriding price ${paymentData.amount} with DB price ${dbPrice}`);
                    paymentData.amount = dbPrice;
                }
                
                paymentCurrency = dbCurrency;
                console.log(`💱 Using plan currency: ${paymentCurrency}`);
            }

            // Track purchase intent
            if (window.WebhookManager) {
                await window.WebhookManager.trackPurchaseIntent({
                    plan: paymentData.plan,
                    amount: paymentData.amount,
                    currency: paymentCurrency,
                    email: paymentData.customer.email,
                    phone: paymentData.customer.phone
                });
            }

            // Build dynamic checkout configurations
            const paymentOptions = window.OJGCurrency 
                ? window.OJGCurrency.getPaymentMethods(paymentCurrency)
                : "card,mobilemoney,ussd,banktransfer";

            const flutterwaveConfig = {
                public_key: this.config.publicKey,
                tx_ref: this.generateTransactionRef(paymentData.plan),
                amount: paymentData.amount,
                currency: paymentCurrency,
                country: paymentCurrency === 'NGN' ? 'NG' : (paymentCurrency === 'KES' ? 'KE' : (paymentCurrency === 'ZAR' ? 'ZA' : 'US')),
                payment_options: paymentOptions,
                customer: {
                    email: paymentData.customer.email,
                    phone_number: paymentData.customer.phone,
                    name: paymentData.customer.name,
                },
                customizations: {
                    title: paymentData.title || this.getProductTitle(),
                    description: paymentData.description || this.getProductDescription(),
                    logo: this.getProductLogo()
                },
                callback: (response) => this.handlePaymentCallback(response, paymentData),
                onclose: () => this.handlePaymentClose(paymentData)
            };

            // Launch Flutterwave payment modal
            window.FlutterwaveCheckout(flutterwaveConfig);

        } catch (error) {
            console.error('❌ Payment processing failed:', error);
            throw error;
        }
    }

    /**
     * Handle successful payment callback
     */
    async handlePaymentCallback(response, originalPaymentData) {
        console.log('💳 Payment callback received:', response);

        if (response.status === 'successful' || response.status === 'completed') {
            console.log('✅ Payment successful! Transaction ID:', response.transaction_id);

            // Try to confirm purchase with webhook manager (BLOCKING for PCOS to get credentials)
            if (window.WebhookManager) {
                try {
                    const confirmResult = await window.WebhookManager.confirmPurchase({
                        transactionId: response.transaction_id,
                        flutterwaveRef: response.tx_ref,
                        amount: response.amount,
                        currency: response.currency,
                        plan: originalPaymentData.plan,
                        customer: originalPaymentData.customer,
                        status: 'completed'
                    });
                    console.log('✅ Purchase confirmation sent to backend');

                    if (confirmResult && confirmResult.credentials) {
                        localStorage.setItem('ojg_new_user_creds', JSON.stringify(confirmResult.credentials));
                    }
                    if (confirmResult && confirmResult.auto_login_token) {
                        localStorage.setItem('ojg_auto_login', confirmResult.auto_login_token);
                    }
                } catch (webhookError) {
                    console.warn('⚠️ Webhook confirmation failed (payment still successful):', webhookError);
                }
            }

            // Always redirect to thank you page in the current funnel directory
            const currentDir = window.location.origin + window.location.pathname.replace(/[^/]*$/, '');
            
            // Determine redirect URL based on delivery preference
            let redirectPage = 'thank-you.html';
            if (originalPaymentData.meta && originalPaymentData.meta.delivery_preference === 'pdf') {
                redirectPage = 'generating-plan.html';
            }
            
            const thankYouUrl = new URL(redirectPage, currentDir);
            thankYouUrl.searchParams.set('tx_ref', response.tx_ref);
            thankYouUrl.searchParams.set('transaction_id', response.transaction_id);
            thankYouUrl.searchParams.set('plan', originalPaymentData.plan);
            thankYouUrl.searchParams.set('amount', response.amount);
            thankYouUrl.searchParams.set('status', 'success');
            thankYouUrl.searchParams.set('delivery', originalPaymentData.meta ? originalPaymentData.meta.delivery_preference : 'web');

            // Pass complete customer data to thank you page
            if (originalPaymentData.customer) {
                thankYouUrl.searchParams.set('name', originalPaymentData.customer.name || '');
                thankYouUrl.searchParams.set('email', originalPaymentData.customer.email || '');
                thankYouUrl.searchParams.set('phone', originalPaymentData.customer.phone || originalPaymentData.customer.phone_number || '');
            }

            console.log('🎉 Redirecting to thank you page:', thankYouUrl.toString());
            window.location.href = thankYouUrl.toString();

        } else {
            // Only show error if payment actually failed at Flutterwave
            console.error('❌ Payment failed at Flutterwave:', response);

            if (window.WebhookManager) {
                window.WebhookManager.trackPurchaseFailed({
                    plan: originalPaymentData.plan,
                    amount: response.amount || originalPaymentData.amount,
                    email: originalPaymentData.customer.email,
                    transaction_id: response.transaction_id,
                    status: response.status,
                    tx_ref: response.tx_ref
                });
            }

            alert('Payment was not successful. Status: ' + response.status + '. Please try again or contact support.');
        }
    }

    /**
     * Handle payment modal close
     */
    handlePaymentClose(paymentData) {
        console.log('💳 Payment modal closed by user');
        if (window.WebhookManager && paymentData) {
            window.WebhookManager.trackAbandonment({
                plan: paymentData.plan,
                amount: paymentData.amount,
                email: paymentData.customer.email,
                reason: 'Modal Closed'
            });
        }
    }

    /**
     * Get product title based on current funnel
     */
    getProductTitle() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'egbon') return "Egbon Instant Herbal";
        if (funnel === 'pcos') return "PCOS Treatment Plan";
        if (funnel === 'acne') return "Acne Treatment Plan";
        if (funnel === 'weight') return "Weight Loss Plan";
        return "Treatment Plan";
    }

    /**
     * Get product description based on current funnel
     */
    getProductDescription() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'egbon') return "Men's natural vitality formula";
        if (funnel === 'pcos') return "Complete PCOS management solution";
        if (funnel === 'acne') return "Natural acne treatment solution";
        if (funnel === 'weight') return "Personalized weight loss program";
        return "Natural health solution";
    }

    /**
     * Get product logo based on current funnel
     */
    getProductLogo() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'egbon') return "https://ojg.ng/egbon-logo.png";
        if (funnel === 'pcos') return "https://ojg.ng/pcos-logo.png";
        if (funnel === 'acne') return "https://ojg.ng/acne-logo.png";
        if (funnel === 'weight') return "https://ojg.ng/weight-logo.png";
        return "https://ojg.ng/logo.png";
    }

    /**
     * Generate unique transaction reference
     */
    generateTransactionRef(planType) {
        const timestamp = Date.now();
        const random = Math.random().toString(36).substring(2, 8);
        const funnel = this.getCurrentFunnel().toUpperCase();
        return `${funnel}_${timestamp}_${random}`;
    }

    /**
     * Validate customer data before payment
     */
    validateCustomerData(customerData) {
        const errors = [];

        if (!customerData.name || customerData.name.trim().length < 2) {
            errors.push('Valid name is required');
        }

        if (!customerData.email || !this.isValidEmail(customerData.email)) {
            errors.push('Valid email address is required');
        }

        if (!customerData.phone || !this.isValidNigerianPhone(customerData.phone)) {
            errors.push('Valid phone number is required (Nigeria, Kenya, or South Africa)');
        }

        return errors;
    }

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone number — supports Nigeria (+234), Kenya (+254), South Africa (+27)
     */
    isValidNigerianPhone(phone) {
        const cleanPhone = phone.replace(/\D/g, '');

        // Nigeria: 0[789]XX or 234[789]XX or [789]XX (10-13 digits)
        const nigeriaPattern = /^(0[789][01]\d{8}|234[789][01]\d{8}|[789][01]\d{8})$/;

        // Kenya: 07XX or 01XX or 2547XX or 2541XX (10-12 digits)
        const kenyaPattern = /^(0[71]\d{8}|254[71]\d{8}|[71]\d{8})$/;

        // South Africa: 0[6-8]X or 27[6-8]X (10-11 digits)
        const saPattern = /^(0[6-8]\d{8}|27[6-8]\d{8}|[6-8]\d{8})$/;

        return nigeriaPattern.test(cleanPhone) || kenyaPattern.test(cleanPhone) || saPattern.test(cleanPhone);
    }

    /**
     * Format phone number with international prefix — supports NG, KE, ZA
     */
    formatNigerianPhone(phone) {
        const cleanPhone = phone.replace(/\D/g, '');

        // Already has international prefix
        if (cleanPhone.startsWith('234') && cleanPhone.length >= 13) {
            return '+' + cleanPhone;
        }
        if (cleanPhone.startsWith('254') && cleanPhone.length >= 12) {
            return '+' + cleanPhone;
        }
        if (cleanPhone.startsWith('27') && cleanPhone.length >= 11) {
            return '+' + cleanPhone;
        }

        // Detect country by leading digit pattern and add prefix
        if (cleanPhone.startsWith('0')) {
            const secondDigit = cleanPhone[1];
            // Kenya: starts with 07 or 01
            if ((secondDigit === '7' || secondDigit === '1') && cleanPhone.length === 10) {
                return '+254' + cleanPhone.substring(1);
            }
            // South Africa: starts with 06, 07, 08
            if ((secondDigit === '6' || secondDigit === '8') && cleanPhone.length === 10) {
                return '+27' + cleanPhone.substring(1);
            }
            // Nigeria: starts with 07, 08, 09
            if ((secondDigit === '7' || secondDigit === '8' || secondDigit === '9') && cleanPhone.length === 11) {
                return '+234' + cleanPhone.substring(1);
            }
        }

        // Fallback: assume Nigeria for 10-digit numbers
        if (cleanPhone.length === 10) {
            return '+234' + cleanPhone;
        }

        return phone;
    }
}

// Global instance
window.FlutterwaveIntegration = new FlutterwaveIntegration();

// Export for module systems if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FlutterwaveIntegration;
}

console.log('💳 Flutterwave Integration loaded');
