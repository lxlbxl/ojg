# Egbon Instant Herbal - Sales Funnel

A complete sales funnel for Egbon Instant Herbal, a natural men's vitality supplement by OJG.

## Funnel Structure

This funnel consists of 5 pages designed to convert visitors into customers:

### 1. Landing Page (`index.html`)
- **Purpose**: Capture attention and drive to assessment
- **Key Elements**:
  - Hero section with value proposition
  - Problem/solution framework
  - Product preview with Egbon bottle image
  - Social proof testimonials
  - Multiple CTAs to assessment
- **Target Audience**: Men experiencing performance issues, low libido, or decreased vitality

### 2. Assessment Page (`assessment.html`)
- **Purpose**: Qualify leads and collect contact information
- **Features**:
  - 10-question interactive assessment
  - Questions about libido, performance, energy, stress, lifestyle
  - Progress bar for user engagement
  - Auto-advance between questions
  - Contact form after assessment completion
  - Performance type calculation (hormonal, stress, lifestyle, age-related)
- **Data Collection**: Name, email, phone, age range

### 3. Results Page (`results.html`)
- **Purpose**: Present personalized results and introduce product
- **Features**:
  - Personalized performance type analysis
  - Common symptoms and root causes
  - Quick action tips
  - Problem intensification
  - Product introduction with benefits
  - Urgency messaging
  - CTA to sales page
- **Performance Types**:
  - Hormonal Imbalance Type
  - Stress-Related Performance Type
  - Lifestyle-Related Performance Type
  - Age-Related Performance Type

### 4. Sales Page (`sales.html`)
- **Purpose**: Convert qualified leads into customers
- **Key Elements**:
  - Product showcase with Egbon bottle
  - Pricing: ₦15,000 (discounted from ₦25,000)
  - Benefits grid (6 key benefits)
  - Customer testimonials
  - 48-hour countdown timer
  - FAQ section
  - Payment form with Flutterwave integration
  - 30-day money-back guarantee
- **Payment**: Integrated with Flutterwave for secure transactions

### 5. Thank You Page (`thank-you.html`)
- **Purpose**: Confirm purchase and set expectations
- **Features**:
  - Order confirmation message
  - Next steps (email, contact, delivery)
  - Product usage guide (Do's and Don'ts)
  - Transformation timeline
  - Customer support information
  - Social proof statistics

## Design & Branding

### Color Scheme
- **Primary**: Dark theme (gray-900, gray-800)
- **Accent**: Gold/Orange (#f59e0b, #d97706) - masculine, premium
- **Highlights**: Blue accents for trust

### Typography
- Bold, masculine fonts
- Clear hierarchy
- Mobile-responsive sizing

### Images
- Egbon Logo: `https://ojg.ng/egbon-logo.png`
- Product Bottle: `https://ojg.ng/egbon-bottle.png`

## Technical Stack

- **Frontend Framework**: TailwindCSS (via CDN)
- **JavaScript Framework**: Alpine.js (via CDN)
- **Payment Integration**: Flutterwave (`../js/flutterwave-integration.js`)
- **Analytics**: Webhook Manager (`../js/webhook-manager.js`)
- **Storage**: LocalStorage for assessment data

## Data Flow

1. **Landing Page** → User clicks CTA
2. **Assessment** → User completes 10 questions → Submits contact info
3. **Results** → Assessment data stored in localStorage as `egbonAssessmentData`
4. **Sales** → Pre-fills customer data from assessment → Payment processing
5. **Thank You** → Order confirmation and next steps

## Integration Points

### Webhook Manager
Tracks conversions at key points:
- Landing page views
- Assessment starts
- Results page views
- Sales page visits
- Purchases

### Flutterwave Integration
Handles payment processing:
- Customer data validation
- Phone number formatting
- Secure payment modal
- Transaction tracking

## Conversion Optimization Features

1. **Scarcity**: 48-hour countdown timer
2. **Social Proof**: Customer testimonials and statistics
3. **Risk Reversal**: 30-day money-back guarantee
4. **Personalization**: Assessment-based recommendations
5. **Urgency**: Limited-time discount messaging
6. **Trust Signals**: Natural ingredients, clinical testing
7. **Clear CTAs**: Multiple conversion points throughout funnel

## Mobile Responsiveness

All pages are fully responsive with:
- Mobile-first design approach
- Touch-friendly buttons and forms
- Optimized images
- Readable typography on small screens

## Setup Instructions

1. Ensure the following files are in the parent directory:
   - `js/webhook-manager.js`
   - `js/flutterwave-integration.js`

2. Update Flutterwave configuration with your:
   - Public key
   - Webhook endpoints

3. Customize contact information:
   - Support email
   - WhatsApp number

4. Test the complete funnel flow before going live

## Customization

### Pricing
Update prices in `sales.html`:
- Regular price: ₦25,000
- Discounted price: ₦15,000

### Product Information
- Update product images URLs if hosting elsewhere
- Modify testimonials with real customer feedback
- Adjust timeline expectations based on actual results

### Assessment Questions
Modify questions in `assessment.html` to better match your target audience

## Performance Metrics to Track

1. Landing page conversion rate (to assessment)
2. Assessment completion rate
3. Results to sales page conversion
4. Sales page conversion rate
5. Overall funnel conversion rate
6. Average order value
7. Customer acquisition cost

## Best Practices

1. **A/B Testing**: Test different headlines, CTAs, and pricing
2. **Load Speed**: Optimize images and minimize external scripts
3. **Mobile Testing**: Test on various devices and screen sizes
4. **Payment Testing**: Test payment flow thoroughly before launch
5. **Analytics**: Monitor drop-off points and optimize accordingly

## Support

For technical support or customization requests, contact the development team.

---

**Product**: Egbon Instant Herbal  
**Brand**: OJG  
**Target Market**: Nigerian men (25-65 years)  
**Primary Benefit**: Natural enhancement of libido, performance, and masculine vitality
