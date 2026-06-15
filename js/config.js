// Configuration file for OJG Herbal sales pages
// Single source of truth for all copy, pricing, social proof, guarantee, and funnel content.

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

  // ─── Social Proof — ONE true number per funnel, used everywhere ────────────
  socialProof: {
    pcos:   { count: "500+", label: "women",  phrase: "500+ women we've helped" },
    acne:   { count: "500+", label: "people", phrase: "500+ people we've helped" },
    weight: { count: "500+", label: "people", phrase: "500+ people we've helped" },
    mens:   { count: "500+", label: "men",    phrase: "500+ men we've helped"    }
  },

  // ─── Guarantee — ONE definition, bound everywhere ─────────────────────────
  guarantee: {
    days:  90,
    label: "90-Day Money-Back Guarantee",
    shortLabel: "90-Day Guarantee",
    badgeText: "🛡️ 90-Day Guarantee",
    bodyText: "Try the full 90-day protocol. If your symptoms haven't improved, email us for a full refund — and keep everything. The only risk is staying where you are.",
    bullets: ["Full refund", "Keep everything", "5-minute process"]
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
    },
    "mens-90-day-plan": {
      originalPrice: 47000,
      salePrice: 22500,
      currency: "NGN",
      discount: 44,
      features: [
        "Personalized 90-Day Vitality Protocol",
        "Custom Nigerian Herb Sourcing Guide",
        "Weekly Progress Tracking Templates",
        "24/7 WhatsApp Support Group Access",
        "Testosterone-Support Recipe Collection",
        "Exercise & Lifestyle Modification Guide"
      ]
    }
  },

  // ─── Value Stack — what's included, with honest itemized values ────────────
  valueStack: {
    pcos: [
      { icon: "🥗", title: "Smart-Personalized Daily Meals",     desc: "Nigerian recipes targeting your specific hormonal imbalance",       value: 45000 },
      { icon: "🍵", title: "Morning & Evening Herbal Rituals",   desc: "Type-specific blends with daily reminders & tracking",              value: 35000 },
      { icon: "🚶‍♀️", title: "Phase-Synced Movement Protocols",  desc: "Cycle-aware activity plans that adapt to your body",                value: 27000 },
      { icon: "📊", title: "Body Data & Progress Tracking",      desc: "Log weight, cycles, symptoms — watch transformation unfold",        value: 90000 }
    ],
    acne: [
      { icon: "🌿", title: "Personalised Herbal Skin Protocol",  desc: "Type-matched botanical blend for your specific acne driver",        value: 40000 },
      { icon: "🥑", title: "Skin-Clear Nutrition Guide",         desc: "Targeted Nigerian meals that starve bacteria and inflammation",     value: 30000 },
      { icon: "📋", title: "Daily Skin Ritual Tracker",         desc: "Morning/evening routines with reminders & progress logging",        value: 25000 },
      { icon: "📊", title: "Breakout Pattern Analysis",          desc: "Track triggers and watch your skin transform week by week",         value: 102000 }
    ],
    weight: [
      { icon: "🔥", title: "Metabolic Reset Meal Plan",          desc: "Nigerian recipes calibrated to your weight type",                   value: 42000 },
      { icon: "🌿", title: "Herbal Ignite Blend",                desc: "Metabolism-boosting herbs targeting visceral fat",                  value: 35000 },
      { icon: "🏃", title: "Type-Matched Movement Protocol",     desc: "Activity plan synced to your body's current capacity",             value: 27000 },
      { icon: "📊", title: "Weight & Body Metrics Tracker",      desc: "Log measurements and habits — watch your transformation unfold",   value: 93000 }
    ],
    mens: [
      { icon: "🥗", title: "Testosterone-Support Meal Plan",     desc: "Nigerian recipes that target your specific vitality driver",        value: 45000 },
      { icon: "🍵", title: "Morning & Evening Herbal Rituals",   desc: "Type-specific adaptogenic blends with daily reminders",            value: 35000 },
      { icon: "💪", title: "Strength-Synced Movement Protocols", desc: "Activity plans that optimise natural hormone production",          value: 27000 },
      { icon: "📊", title: "Vitality Data & Progress Tracking",  desc: "Log energy, sleep, strength — watch transformation unfold",        value: 90000 }
    ]
  },

  // ─── Order Bumps — pre-checkout add-on per funnel ─────────────────────────
  orderBump: {
    pcos:   { title: "Add 1-on-1 WhatsApp Expert Access (30 days)", desc: "A certified PCOS nutrition coach in your pocket. Daily message support, recipe swaps, and a personal 15-min check-in call on day 14.", price: 17000, badge: "87% Add This" },
    acne:   { title: "Add Personalised Herbal Acne Pack (30-day supply)", desc: "Pre-measured herbal sachets delivered to your door — no measuring, no guesswork. Your protocol, ready to brew.", price: 22000, badge: "82% Add This" },
    weight: { title: "Add 1-on-1 WhatsApp Expert Access (30 days)", desc: "A certified weight-loss coach in your pocket. Daily accountability check-ins and real-time meal swaps.", price: 17000, badge: "79% Add This" },
    mens:   { title: "Add 1-on-1 WhatsApp Expert Access (30 days)", desc: "A certified men's wellness coach in your pocket. Daily message support and a personal 15-min check-in on day 14.", price: 17000, badge: "84% Add This" }
  },

  // ─── Funnel-Specific Content (empathy, mechanism, cost-of-inaction, FAQ) ──
  FUNNEL_CONFIG: {
    pcos: {
      audience: "women",
      conditionName: "PCOS",
      // Empathy beat — disarms skeptics BEFORE the pitch
      empathy: {
        heading: "First — this wasn't your fault.",
        body: "If you've been dismissed by doctors, told to 'just lose weight' or 'go on the pill,' tried every supplement with zero results, and wondered why your body feels like it's working against you — that experience is real, and you deserve better. Here's what's actually been happening inside your body."
      },
      // Mechanism — why type-specific works when generic doesn't
      mechanism: {
        heading: "Why Generic PCOS Advice Has Failed You",
        subheading: "Most advice treats all PCOS the same. It isn't.",
        steps: [
          { icon: "❌", title: "Generic advice ignores your type", body: "Insulin-resistant PCOS requires the opposite approach to adrenal PCOS. Treating them the same is like prescribing the same glasses to everyone." },
          { icon: "🧬", title: "Your body has a specific driver", body: "Your assessment identified your primary PCOS type. That single fact changes everything — the herbs, the meals, and the movement that works for you." },
          { icon: "✅", title: "Type-specific protocols work differently", body: "Our protocol is built around YOUR type. That's why women who've 'tried everything' finally see results — because for the first time, the approach actually matches the problem." }
        ]
      },
      // Cost of inaction — ethical urgency, no fake deadlines
      costOfInaction: {
        heading: "What Continuing As-Is Actually Costs",
        points: [
          "Insulin resistance worsens year by year — making future treatment harder and fertility windows shorter.",
          "Untreated PCOS increases long-term risk of type 2 diabetes and heart disease.",
          "Another cycle of buying supplements that don't match your type and getting nowhere.",
          "The emotional toll of feeling dismissed, unheard, and stuck — which compounds over time."
        ]
      },
      // FAQ for select-plan page
      faq: [
        { q: "I've tried everything — how is this different?", a: "Most things you've tried were built for 'PCOS in general.' Your protocol is built around your specific type. Insulin-resistant PCOS requires fundamentally different interventions than adrenal or post-pill PCOS — and ours are matched to your results." },
        { q: "Is this medical advice?", a: "No — this is a wellness and lifestyle guide designed to complement your healthcare. It works alongside your doctor, not instead of one. We recommend sharing your plan with your GP, especially if you're on medication." },
        { q: "I'm on medication — is this safe?", a: "Our herbal protocols are food-grade and generally safe alongside most medications. That said, always consult your doctor before starting any new supplement, especially if you're on metformin or hormonal medication." },
        { q: "What exactly do I get and how fast will I see results?", a: "You receive a full 90-day protocol: daily meal plans, morning/evening herbal rituals, a movement schedule, and a progress tracking portal. Most women notice changes in energy and cycle regularity within weeks 3–6, with significant hormonal shifts by week 8–12." },
        { q: "What if it doesn't work for me?", a: "That's what the 90-day guarantee is for. If you follow the protocol and don't see improvement, email us and we'll refund every kobo — and you keep everything. No questions, no hassle." },
        { q: "Is my payment secure?", a: "Yes. All payments are processed by Flutterwave — Nigeria's leading payment processor — with 256-bit SSL encryption. Your card details are never stored by us." },
        { q: "Can I access this internationally?", a: "Absolutely. The portal is fully online. International buyers pay in USD via card. The herbal knowledge and protocols are adapted for globally available ingredients alongside Nigerian-sourced options." }
      ]
    },
    acne: {
      audience: "people",
      conditionName: "acne",
      images: {
        dashboard: "../images/acne/dashboard-mockup.png",
        transformation: "../images/acne/glowing-skin-confidence.png",
        mechanism: "../images/acne/follicle-mechanism.png",
        types: {
          hormonal: "../images/acne/type-hormonal.png",
          inflammatory: "../images/acne/type-inflammatory.png",
          comedonal: "../images/acne/type-comedonal.png",
          cystic: "../images/acne/type-cystic.png"
        }
      },
      empathy: {
        heading: "First — this wasn't your fault.",
        body: "If you've tried every cleanser, prescription cream, and skincare routine only to watch your skin break out again — that's not a willpower problem. That's a biology problem. You weren't using the wrong products. You were treating the surface of a problem that lives deep inside."
      },
      mechanism: {
        heading: "Why Standard Skincare Has Failed Your Skin",
        subheading: "Topical treatments can't fix an internal problem.",
        steps: [
          { icon: "❌", title: "Topicals treat the symptom, not the cause", body: "Acne begins with hormonal imbalance, gut dysbiosis, or chronic inflammation — none of which a face wash can fix." },
          { icon: "🧬", title: "Your specific acne type has a specific driver", body: "Hormonal acne, cystic acne, comedonal acne, and inflammatory acne each have different root causes. Treating them the same is why generic advice fails." },
          { icon: "✅", title: "Internal reset, not external cover-up", body: "Our protocol uses specific Nigerian botanicals to reset the internal environment that's driving your breakouts — so clear skin becomes your new baseline." }
        ]
      },
      costOfInaction: {
        heading: "What Continuing As-Is Actually Costs",
        points: [
          "Scarring — repeated breakouts cause cumulative skin damage that becomes harder to reverse over time.",
          "Ongoing spend on products that provide temporary relief but never fix the root cause.",
          "The confidence cost — avoiding cameras, social events, and close-up conversations.",
          "The frustration of trying system after system with no lasting result."
        ]
      },
      faq: [
        { q: "I've tried everything — how is this different?", a: "Most products treat acne at the skin surface. Our protocol identifies your specific acne type and resets the internal driver — whether that's hormonal imbalance, gut dysbiosis, or chronic inflammation. That's the difference between masking and solving." },
        { q: "Is this medical advice?", a: "No — this is a wellness guide designed to complement your skincare and healthcare. If you're under a dermatologist, this works alongside their advice." },
        { q: "I'm on medication — is this safe?", a: "Our botanicals are food-grade and safe for most people. If you're on isotretinoin or antibiotics, please consult your doctor before starting any herbal protocol." },
        { q: "How fast will I see results?", a: "Most people see a reduction in new breakouts within weeks 2–4. Significant clearing typically happens between weeks 4–8. Deep cystic acne may take up to 12 weeks as the internal environment resets." },
        { q: "What if it doesn't work for me?", a: "Our 90-day guarantee means zero risk for you. If you follow the protocol and your skin hasn't improved, email us for a full refund — and you keep everything." },
        { q: "Is my payment secure?", a: "Yes. Payments are processed by Flutterwave with 256-bit SSL encryption. Your card details are never stored by us." },
        { q: "Do I need to buy special products?", a: "No imported supplements required. The protocol uses Nigerian-sourced or globally available herbal ingredients alongside accessible whole foods." }
      ]
    },
    weight: {
      audience: "people",
      conditionName: "weight",
      empathy: {
        heading: "First — this wasn't your fault.",
        body: "If you've cut calories, tried every diet, pushed through workouts, and watched the scale refuse to move — that's not a lack of willpower. That's a metabolic or hormonal problem that conventional dieting is not designed to fix. You weren't doing it wrong. You were using the wrong tool for your specific body."
      },
      mechanism: {
        heading: "Why Every Diet Has Failed You",
        subheading: "Calories in, calories out ignores your biology.",
        steps: [
          { icon: "❌", title: "Diets are generic, your metabolism is specific", body: "Hormonal weight gain, emotional eating, and metabolic resistance each require completely different interventions. A generic calorie-deficit plan has no idea which one you have." },
          { icon: "🧬", title: "Your weight type has a specific root cause", body: "Your assessment identified whether your weight is driven by hormones, metabolism, stress, or lifestyle. That single fact changes which foods, herbs, and habits will actually work for you." },
          { icon: "✅", title: "Fix the root cause, not just the calorie count", body: "Our protocol addresses the underlying driver — resetting the hormones, metabolism, or habits that are keeping your body in storage mode." }
        ]
      },
      costOfInaction: {
        heading: "What Continuing As-Is Actually Costs",
        points: [
          "Untreated hormonal or metabolic imbalances worsen over time — making future weight loss progressively harder.",
          "The diet cycle: restricting, failing, blaming yourself, restricting again — a loop with no exit without addressing the root cause.",
          "Continued spend on plans that treat generic weight loss rather than your specific type.",
          "Long-term health risks from sustained metabolic imbalance or elevated cortisol."
        ]
      },
      faq: [
        { q: "I've tried everything — how is this different?", a: "Most weight loss plans are built on calorie restriction. Yours is built on your specific driver — hormonal, metabolic, emotional, or lifestyle. That's the only reason our results are different from what you've tried before." },
        { q: "Is this medical advice?", a: "No — this is a wellness and lifestyle guide designed to work alongside your healthcare. We recommend sharing your plan with your doctor, especially if you have thyroid or insulin conditions." },
        { q: "I'm on medication — is this safe?", a: "Our herbal and nutritional protocol is food-grade. If you're on thyroid, diabetes, or blood pressure medication, consult your doctor before starting." },
        { q: "How fast will I see results?", a: "Most people notice increased energy and reduced cravings within weeks 1–2. Visible weight changes typically appear from weeks 3–6. The 90-day protocol is designed for sustainable, lasting transformation rather than rapid but temporary loss." },
        { q: "What if it doesn't work for me?", a: "You're protected by our 90-day guarantee. Follow the protocol and if you don't see measurable progress, email us for a full refund — and keep everything." },
        { q: "Is my payment secure?", a: "Yes. Payments are processed by Flutterwave with 256-bit SSL encryption. Your card details are never stored." },
        { q: "Will I have to eat foods I don't like?", a: "No. The protocol is built around Nigerian food culture and globally available whole foods. No strange imported ingredients or meal replacement shakes." }
      ]
    },
    mens: {
      audience: "men",
      conditionName: "vitality",
      empathy: {
        heading: "First — this is not 'just age.'",
        body: "If you've been told low energy, reduced drive, difficulty building muscle, or mental fog are 'a normal part of getting older' — that's not the full picture. These are symptoms of a measurable biological imbalance. They respond to targeted intervention. You were right to look for answers."
      },
      mechanism: {
        heading: "Why Generic Men's Health Advice Hasn't Worked",
        subheading: "Not all vitality decline has the same driver.",
        steps: [
          { icon: "❌", title: "Generic advice treats all men the same", body: "Hormonal decline driven by chronic stress (adrenal) is completely different from insulin resistance or inflammatory suppression. A generic testosterone supplement doesn't know the difference." },
          { icon: "🧬", title: "Your type has a specific biological driver", body: "Your assessment identified your primary vitality driver. That one finding changes which herbs, foods, and lifestyle interventions will actually move the needle for you." },
          { icon: "✅", title: "Type-specific protocols work differently", body: "By matching the intervention to the driver, men who've 'tried the standard stuff' finally experience real change — because the approach now matches the problem." }
        ]
      },
      costOfInaction: {
        heading: "What Continuing As-Is Actually Costs",
        points: [
          "Hormonal imbalances compound over time — the earlier you address the root cause, the faster and more complete the recovery.",
          "Sustained low testosterone or high cortisol increases long-term risk of cardiovascular disease and metabolic syndrome.",
          "Continued spend on generic supplements that are mismatched to your actual driver.",
          "The relationship, productivity, and confidence cost of operating below your potential energy."
        ]
      },
      faq: [
        { q: "I've tried testosterone boosters — how is this different?", a: "Most boosters are generic and untargeted. Your protocol is built around your specific vitality driver — adrenal, inflammatory, metabolic, or hormonal. The right intervention for your type is completely different." },
        { q: "Is this medical advice?", a: "No — this is a wellness and lifestyle guide designed to complement your healthcare. If you're under a doctor for hormonal issues, this works alongside their advice, not instead of it." },
        { q: "I'm on medication — is this safe?", a: "Our herbal protocol is food-grade. If you're on blood pressure, diabetes, or hormonal medication, consult your doctor before starting." },
        { q: "How fast will I see results?", a: "Most men notice improved energy and sleep quality within weeks 1–3. Strength and drive improvements typically appear from weeks 4–8. Full hormonal reset takes the complete 90-day protocol." },
        { q: "What if it doesn't work for me?", a: "You're covered by our 90-day guarantee. Follow the protocol and if you don't see measurable improvement, email us for a full refund — and keep everything." },
        { q: "Is my payment secure?", a: "Yes. Payments are processed by Flutterwave with 256-bit SSL encryption. Your card details are never stored by us." },
        { q: "Is this confidential?", a: "Completely. Your assessment data, order, and support conversations are private. No information is shared with third parties." }
      ]
    }
  },

  // ─── Type-Tagged Testimonials for all funnels ─────────────────────────────
  testimonials: {
    pcos: [
      {
        name: "Sarah M.", city: "Lagos", type: "insulin",
        headline: "My Period Returned After 8 Months",
        quote: "Finally got my period back after 8 months! The personalized plan made all the difference. I tried everything before — this was the first thing that actually worked.",
        tags: ["Period returned", "-7kg"], rating: 5, verified: true
      },
      {
        name: "Funmi A.", city: "Abuja", type: "inflammatory",
        headline: "Lost 15kg and My A1C Normalised",
        quote: "Lost 15kg in 3 months! My insulin resistance is finally under control. The Nigerian meals were so easy to follow — no expensive imported foods needed.",
        tags: ["-15kg", "A1C normalized"], rating: 5, verified: true
      },
      {
        name: "Kemi O.", city: "Port Harcourt", type: "adrenal",
        headline: "Clear Skin and 3x the Energy",
        quote: "My acne cleared up completely and my energy is through the roof! I was sleeping better by week 2. The herbal teas changed my life.",
        tags: ["Clear skin", "Energy 3x"], rating: 5, verified: true
      },
      {
        name: "Blessing I.", city: "Ibadan", type: "postPill",
        headline: "Cycle Regulated After 8 Months Off the Pill",
        quote: "8 months off the pill and my cycle finally regulated. The protocol addressed the post-pill hormone chaos directly. I was starting to lose hope.",
        tags: ["Cycle regular", "No more cramps"], rating: 5, verified: true
      }
    ],
    acne: [
      {
        name: "Adaeze O.", city: "Lagos", type: "hormonal",
        headline: "Jawline Acne Gone in 6 Weeks",
        quote: "My jawline breakouts cleared in 6 weeks. The protocol addressed the hormonal root cause — not just the surface pimples. I haven't had a hormonal breakout since.",
        tags: ["Clear skin", "Cycle regular"], rating: 5, verified: true
      },
      {
        name: "Chioma N.", city: "Abuja", type: "inflammatory",
        headline: "Redness and -12kg in 3 Months",
        quote: "Lost the redness and 12kg in 3 months. The anti-inflammatory herbal blends worked faster than any cream I had tried before. My skin is calm for the first time in years.",
        tags: ["-12kg", "No more redness"], rating: 5, verified: true
      },
      {
        name: "Tola B.", city: "Ibadan", type: "comedonal",
        headline: "No New Blackheads in 8 Weeks",
        quote: "My blackheads and whiteheads finally stopped forming. I have not had a new closed comedone in 8 weeks. My texture has completely changed.",
        tags: ["Smooth texture", "No new bumps"], rating: 5, verified: true
      },
      {
        name: "Ngozi E.", city: "Port Harcourt", type: "cystic",
        headline: "Cystic Lumps Resolved — No More Injections",
        quote: "The deep cystic lumps I had for years finally resolved. No more painful injections at the dermatologist. My skin is calm and smooth.",
        tags: ["Pain-free", "No scars"], rating: 5, verified: true
      }
    ],
    weight: [
      {
        name: "Chinwe O.", city: "Lagos", type: "hormonal",
        headline: "A Hormonal Weight Breakthrough",
        quote: "I had tried every diet. Nothing worked. The 90-Day plan reset my hormones and the weight finally came off. I have my confidence back.",
        tags: ["-18kg", "Hormones balanced"], rating: 5, verified: true
      },
      {
        name: "Funmi A.", city: "Abuja", type: "emotional",
        headline: "Broke the Emotional Eating Cycle",
        quote: "I never knew my cravings were tied to stress. Once I addressed the root cause, the binge cycle stopped. 12kg down in 90 days and it's stayed off.",
        tags: ["-12kg", "Cravings gone"], rating: 5, verified: true
      },
      {
        name: "Ngozi E.", city: "Port Harcourt", type: "metabolic",
        headline: "Metabolism Reset — Down 2 Dress Sizes",
        quote: "My metabolism was completely broken. The herbal ignite plus nutrition plan restored it. Down 2 dress sizes and feeling 10 years younger.",
        tags: ["2 dress sizes", "Energy restored"], rating: 5, verified: true
      },
      {
        name: "Bisi T.", city: "Ibadan", type: "lifestyle",
        headline: "14kg Gone and Stayed Off for 6 Months",
        quote: "I changed my habits, not just my plate. The plan walked me through it step by step. 14kg gone, kept off for 6 months now.",
        tags: ["-14kg", "Sustained"], rating: 5, verified: true
      }
    ],
    mens: [
      {
        name: "Emeka O.", city: "Lagos", type: "metabolic",
        headline: "Energy Back, -10kg, Stronger Than My 30s",
        quote: "I was exhausted by noon every day and couldn't shift the belly fat no matter what I tried. The 90-day protocol completely reset my metabolism. More energy than I had in my 30s.",
        tags: ["-10kg", "Energy restored"], rating: 5, verified: true
      },
      {
        name: "Chukwudi A.", city: "Abuja", type: "adrenal",
        headline: "Stress Under Control — Sleeping 8 Hours Again",
        quote: "Chronic work stress had destroyed my sleep and drive. The adrenal recovery blend and the protocol rebuilt my resilience. Sleeping 8 hours and back to full performance.",
        tags: ["Deep sleep", "Stress managed"], rating: 5, verified: true
      },
      {
        name: "Biodun K.", city: "Port Harcourt", type: "inflammatory",
        headline: "Joint Pain Gone, Mental Clarity Back",
        quote: "The joint inflammation and brain fog I had written off as age cleared within 6 weeks. The anti-inflammatory protocol was targeted in a way nothing else I'd tried was.",
        tags: ["Pain-free", "Mental clarity"], rating: 5, verified: true
      },
      {
        name: "Seun T.", city: "Ibadan", type: "insulin",
        headline: "Blood Sugar Controlled, Belly Fat Finally Moving",
        quote: "My doctor had told me I was pre-diabetic. After 90 days on the insulin-reset protocol, my fasting glucose is in the normal range and I've lost 8kg from my midsection.",
        tags: ["Blood sugar normal", "-8kg"], rating: 5, verified: true
      }
    ]
  },

  // Sub-brand voices for funnel personalization (A/B testing)
  subBrands: {
    cyclesync: {
      id: "cyclesync",
      name: "CycleSync",
      voice: "Medical-clinical. Outcome-focused. Authoritative. Mentions cycles, hormones, labs, evidence.",
      tagline: "Evidence-backed herbal protocols for hormonal balance.",
      color: "#7C3AED"
    },
    glowclear: {
      id: "glowclear",
      name: "GlowClear",
      voice: "Warm beauty-wellness. Confident, body-positive. Mentions skin, glow, confidence, ritual.",
      tagline: "Herbal rituals for visibly clear, confident skin.",
      color: "#EC4899"
    },
    leanflow: {
      id: "leanflow",
      name: "LeanFlow",
      voice: "Performance-fitness. Numbers, energy, momentum. Mentions results, energy, momentum, daily wins.",
      tagline: "Sustainable herbal support for natural weight loss momentum.",
      color: "#0EA5E9"
    },
    vitale: {
      id: "vitale",
      name: "Vitale",
      voice: "Heritage-trust. Fatherly, time-tested. Mentions family, tradition, quality, generational wisdom.",
      tagline: "Time-tested Nigerian herbal blends, prepared for modern life.",
      color: "#B45309"
    }
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
  getPricing: function (productId) {
    return CONFIG.pricing[productId] || null;
  },

  // Get testimonials directly from config (all for funnel, or type-matched)
  getTestimonials: function (funnel, type) {
    const pool = CONFIG.testimonials[funnel] || [];
    if (!type) return pool;
    const matched = pool.filter(t => t.type === type);
    return matched.length > 0 ? matched : pool;
  },

  // Get payment configuration directly from config
  getPaymentConfig: function () {
    return CONFIG.payment.flutterwave;
  },

  // Get social proof for a funnel
  getSocialProof: function (funnel) {
    return CONFIG.socialProof[funnel] || CONFIG.socialProof.pcos;
  },

  // Get guarantee copy
  getGuarantee: function () {
    return CONFIG.guarantee;
  },

  // Get funnel-specific content
  getFunnelConfig: function (funnel) {
    return CONFIG.FUNNEL_CONFIG[funnel] || CONFIG.FUNNEL_CONFIG.pcos;
  },

  // Get value stack
  getValueStack: function (funnel) {
    return CONFIG.valueStack[funnel] || CONFIG.valueStack.pcos;
  },

  // Get order bump
  getOrderBump: function (funnel) {
    return CONFIG.orderBump[funnel] || CONFIG.orderBump.pcos;
  }
};

// Make ConfigManager available globally
if (typeof window !== 'undefined') {
  window.ConfigManager = ConfigManager;
  window.CONFIG = CONFIG;
  // Also expose FUNNEL_CONFIG at top level for legacy access
  window.FUNNEL_CONFIG = CONFIG.FUNNEL_CONFIG;
  window.OJG_DATA = { testimonials: CONFIG.testimonials };
}

// Export for Node.js environments
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { CONFIG, ConfigManager };
}