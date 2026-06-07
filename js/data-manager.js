/**
 * Centralized Data Manager for OJG Herbal Sales Pages
 * This approach embeds all configuration data directly in the script
 * to avoid loading issues with external config files
 */

window.DataManager = {
    // Pricing configurations for all products
    pricing: {
        'pcos-90-day-plan': {
            originalPrice: 45000,
            salePrice: 25000,
            currency: 'NGN',
            features: [
                'Complete 90-day PCOS treatment plan',
                'Natural herbal supplements',
                'Personalized diet guide',
                'Exercise recommendations',
                'WhatsApp support group',
                'Free consultation with herbalist',
                'Money-back guarantee'
            ]
        },
        'weight-loss-plan': {
            originalPrice: 35000,
            salePrice: 20000,
            currency: 'NGN',
            features: [
                'Effective weight loss supplements',
                'Customized meal plans',
                'Workout routines',
                'Progress tracking tools',
                'Community support',
                'Expert guidance',
                '30-day money-back guarantee'
            ]
        },
        'acne-treatment-plan': {
            originalPrice: 30000,
            salePrice: 18000,
            currency: 'NGN',
            features: [
                'Natural acne treatment products',
                'Skincare routine guide',
                'Dietary recommendations',
                'Before/after tracking',
                'Expert dermatologist consultation',
                'WhatsApp support',
                'Satisfaction guarantee'
            ]
        }
    },

    // Testimonials for all product categories
    testimonials: {
        pcos: [
            {
                name: "Sarah Johnson",
                location: "Lagos, Nigeria",
                rating: 5,
                text: "After struggling with PCOS for years, this treatment plan completely changed my life. My cycles are regular now and I feel amazing!",
                image: "images/testimonials/sarah.jpg",
                verified: true
            },
            {
                name: "Amina Hassan",
                location: "Abuja, Nigeria", 
                rating: 5,
                text: "I was skeptical at first, but the results speak for themselves. Lost 15kg and my PCOS symptoms are gone!",
                image: "images/testimonials/amina.jpg",
                verified: true
            },
            {
                name: "Grace Okafor",
                location: "Port Harcourt, Nigeria",
                rating: 5,
                text: "The herbal supplements worked better than any medication I tried. Highly recommend to anyone with PCOS!",
                image: "images/testimonials/grace.jpg",
                verified: true
            }
        ],
        weight: [
            {
                name: "Jennifer Adebayo",
                location: "Ibadan, Nigeria",
                rating: 5,
                text: "Lost 20kg in 3 months! The supplements are natural and effective. Best investment I ever made.",
                image: "images/testimonials/jennifer.jpg",
                verified: true
            },
            {
                name: "Kemi Ogundimu",
                location: "Lagos, Nigeria",
                rating: 5,
                text: "Finally found something that works! The weight loss plan is easy to follow and the results are incredible.",
                image: "images/testimonials/kemi.jpg",
                verified: true
            },
            {
                name: "Fatima Bello",
                location: "Kano, Nigeria",
                rating: 5,
                text: "I've tried everything but nothing worked like this. Lost 18kg and feel more confident than ever!",
                image: "images/testimonials/fatima.jpg",
                verified: true
            }
        ],
        acne: [
            {
                name: "Chioma Okwu",
                location: "Enugu, Nigeria",
                rating: 5,
                text: "My acne cleared up completely in just 6 weeks! The natural approach really works.",
                image: "images/testimonials/chioma.jpg",
                verified: true
            },
            {
                name: "Blessing Eze",
                location: "Lagos, Nigeria",
                rating: 5,
                text: "After years of trying different products, this finally gave me clear skin. So grateful!",
                image: "images/testimonials/blessing.jpg",
                verified: true
            },
            {
                name: "Aisha Mohammed",
                location: "Kaduna, Nigeria",
                rating: 5,
                text: "The acne treatment is gentle but effective. My skin has never looked better!",
                image: "images/testimonials/aisha.jpg",
                verified: true
            }
        ]
    },

    // Payment configuration
    paymentConfig: {
        flutterwave: {
            publicKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X",
            secretKey: "FLWSECK_TEST-SANDBOXDEMOKEY-X",
            apiKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X",
            currency: "NGN",
            country: "NG",
            paymentOptions: "card,banktransfer,ussd",
            redirectUrl: window.location.origin + "/payment-success.html",
            meta: {
                source: "ojg-herbal-website",
                medium: "sales-page"
            }
        },
        paystack: {
            publicKey: "pk_test_your-paystack-key-here",
            currency: "NGN"
        }
    },

    // Utility methods to get data
    getPricing: function(planId) {
        console.log(`📊 Getting pricing for: ${planId}`);
        const pricing = this.pricing[planId];
        if (pricing) {
            console.log(`✅ Found pricing:`, pricing);
            return pricing;
        } else {
            console.warn(`⚠️ No pricing found for plan: ${planId}`);
            return null;
        }
    },

    getTestimonials: function(category) {
        console.log(`💬 Getting testimonials for: ${category}`);
        const testimonials = this.testimonials[category];
        if (testimonials && testimonials.length > 0) {
            console.log(`✅ Found ${testimonials.length} testimonials for ${category}`);
            return testimonials;
        } else {
            console.warn(`⚠️ No testimonials found for category: ${category}`);
            return [];
        }
    },

    getPaymentConfig: function() {
        console.log(`💳 Getting payment configuration`);
        if (this.paymentConfig) {
            console.log(`✅ Payment config loaded:`, this.paymentConfig);
            return this.paymentConfig;
        } else {
            console.warn(`⚠️ No payment configuration found`);
            return null;
        }
    },

    // Method to update pricing (for easy editing)
    updatePricing: function(planId, newPricing) {
        console.log(`🔄 Updating pricing for ${planId}:`, newPricing);
        if (this.pricing[planId]) {
            this.pricing[planId] = { ...this.pricing[planId], ...newPricing };
            console.log(`✅ Pricing updated for ${planId}`);
            return true;
        } else {
            console.warn(`⚠️ Plan ${planId} not found for pricing update`);
            return false;
        }
    },

    // Method to add testimonials
    addTestimonial: function(category, testimonial) {
        console.log(`➕ Adding testimonial to ${category}:`, testimonial);
        if (this.testimonials[category]) {
            this.testimonials[category].push(testimonial);
            console.log(`✅ Testimonial added to ${category}`);
            return true;
        } else {
            console.warn(`⚠️ Category ${category} not found for testimonial`);
            return false;
        }
    }
};

// Make it globally available
window.CONFIG = window.DataManager;

console.log('🚀 DataManager loaded successfully!');
console.log('📋 Available pricing plans:', Object.keys(window.DataManager.pricing));
console.log('💬 Available testimonial categories:', Object.keys(window.DataManager.testimonials));