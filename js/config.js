// Configuration file for OJG Herbal sales pages
// This replaces webhook calls with local data

const CONFIG = {
  // Payment Gateway Configuration
  payment: {
    flutterwave: {
      publicKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X",
      environment: "sandbox",
      currency: "NGN",
      country: "NG",
      paymentMethods: ["card", "banktransfer", "ussd"]
    }
  },

  // Pricing Configuration
  pricing: {
    "pcos-90-day-plan": {
      originalPrice: 47000,
      salePrice: 22500,
      currency: "NGN",
      discount: 44,
      features: [
        "Personalized 90-Day Herbal Protocol (Based on YOUR Assessment)",
        "Custom Nigerian Herb Sourcing Guide",
        "Weekly Progress Tracking Templates",
        "24/7 WhatsApp Support Group Access",
        "Hormone Balance Recipe Collection",
        "Exercise & Lifestyle Modification Guide"
      ]
    },
    "acne-treatment-plan": {
      originalPrice: 35000,
      salePrice: 18500,
      currency: "NGN",
      discount: 47,
      features: [
        "Personalized Acne Treatment Protocol",
        "Nigerian Herbal Acne Solutions",
        "Skin Care Routine Guide",
        "Diet & Lifestyle Recommendations",
        "Progress Tracking Tools",
        "Expert Support Access"
      ]
    },
    "weight-loss-plan": {
      originalPrice: 42000,
      salePrice: 21000,
      currency: "NGN",
      discount: 50,
      features: [
        "Personalized Weight Loss Protocol",
        "Nigerian Herbal Weight Loss Guide",
        "Meal Planning Templates",
        "Exercise Recommendations",
        "Progress Tracking System",
        "Community Support Access"
      ]
    }
  },

  // Testimonials Configuration
  testimonials: {
    pcos: [
      {
        name: "Adunni O.",
        location: "Lagos, Nigeria",
        condition: "PCOS",
        result: "Regular periods after 3 months",
        testimonial: "I had irregular periods for over 2 years. After following the herbal protocol, my cycles became regular within 3 months. I'm so grateful!",
        rating: 5,
        verified: true
      },
      {
        name: "Kemi A.",
        location: "Abuja, Nigeria",
        condition: "PCOS",
        result: "Lost 15kg and conceived naturally",
        testimonial: "The herbs helped me lose weight and balance my hormones. I conceived naturally after 18 months of trying. Thank you!",
        rating: 5,
        verified: true
      },
      {
        name: "Funmi S.",
        location: "Ibadan, Nigeria",
        condition: "PCOS",
        result: "Reduced insulin resistance",
        testimonial: "My doctor confirmed my insulin levels improved significantly after 4 months on the protocol. Highly recommended!",
        rating: 5,
        verified: true
      }
    ],
    acne: [
      {
        name: "Tolu M.",
        location: "Port Harcourt, Nigeria",
        condition: "Severe Acne",
        result: "Clear skin in 6 weeks",
        testimonial: "My face was covered with painful cysts. The herbal treatment cleared my skin completely in just 6 weeks!",
        rating: 5,
        verified: true
      },
      {
        name: "Bisi K.",
        location: "Kano, Nigeria",
        condition: "Hormonal Acne",
        result: "No more breakouts",
        testimonial: "I used to break out every month during my cycle. Haven't had a single pimple in 3 months now!",
        rating: 5,
        verified: true
      }
    ],
    weight: [
      {
        name: "Chioma N.",
        location: "Enugu, Nigeria",
        condition: "Obesity",
        result: "Lost 25kg in 6 months",
        testimonial: "I tried everything but nothing worked until I found these herbs. Lost 25kg and feel amazing!",
        rating: 5,
        verified: true
      },
      {
        name: "Amina H.",
        location: "Kaduna, Nigeria",
        condition: "Weight Gain",
        result: "Lost 18kg naturally",
        testimonial: "The herbs helped me lose weight without any side effects. I'm back to my ideal weight!",
        rating: 5,
        verified: true
      }
    ]
  },

  // API Configuration (for future use)
  api: {
    baseUrl: "https://n8n.ai20.city",
    endpoints: {
      leads: "/webhook/pcosLeads",
      pricing: "/webhook/pricing",
      testimonials: "/webhook/testimonials",
      flutterwaveKeys: "/webhook/flutterwave-keys",
      conversion: "/webhook/conversion"
    }
  }
};

// Helper functions to access config data directly
const ConfigManager = {
  // Get pricing data directly from config
  getPricing: function(productId) {
    return CONFIG.pricing[productId] || null;
  },

  // Get testimonials directly from config
  getTestimonials: function(category) {
    return CONFIG.testimonials[category] || [];
  },

  // Get payment configuration directly from config
  getPaymentConfig: function() {
    return CONFIG.payment.flutterwave;
  }
};

// Make ConfigManager available globally
if (typeof window !== 'undefined') {
  window.ConfigManager = ConfigManager;
  window.CONFIG = CONFIG;
}

// Export for Node.js environments
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { CONFIG, ConfigManager };
}