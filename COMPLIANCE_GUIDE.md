# GDPR & NDPR Compliance Guide

## Overview

This guide documents the GDPR (General Data Protection Regulation - EU) and NDPR (Nigeria Data Protection Regulation 2019) compliance features implemented in the OJG Herbal Health Assessment system.

---

## 📋 Compliance Features Implemented

### 1. Privacy Policy
**File:** `privacy-policy.html` (root and `ojg-usd/`)

A comprehensive privacy policy page that includes:
- Data controller information
- Types of data collected (including sensitive health data)
- Legal basis for processing (GDPR Article 6)
- Data subject rights (access, rectification, erasure, portability)
- Data retention periods
- Cookie usage disclosure
- Contact information for DPO

**Access:** `https://yourdomain.com/privacy-policy.html`

---

### 2. Cookie Consent Banner
**File:** `js/cookie-consent.js`

Features:
- Granular consent options (Essential, Analytics, Marketing)
- Consent recording with timestamp and version
- Consent expiry (365 days)
- Withdrawal mechanism
- Audit trail recording to backend
- Customization modal
- LocalStorage-based consent management

**API:** `window.OJGConsent.get()`, `window.OJGConsent.has(category)`, `window.OJGConsent.save(consent)`

---

### 3. Consent Recording API
**File:** `backend/api/record-consent.php`

Records user consent with:
- Session ID tracking
- IP address and user agent
- Page URL where consent was given
- Consent category and status
- Timestamp and version

**Endpoint:** `POST /backend/api/record-consent.php`

---

### 4. GDPR/NDPR Admin Dashboard
**File:** `backend/admin/gdpr-ndpr.php`

Features:
- Compliance statistics overview
- Data Subject Access Request (DSAR) processing
- User data export (JSON download)
- User data deletion (anonymization)
- Privacy settings configuration
- Compliance report generation
- Checklist tracking

**Access:** `https://yourdomain.com/backend/admin/gdpr-ndpr.php` (admin login required)

---

### 5. Consent Checkbox Component
**File:** `backend/components/consent-checkbox.html`

Reusable component for forms with:
- Required privacy consent checkbox
- Required health data consent checkbox
- Optional marketing consent checkbox
- Rights notice footer
- Form validation integration

**Usage:** Include in assessment and sales forms

---

### 6. Database Schema
**File:** `backend/database/gdpr_schema.sql`

Creates tables:
- `consent_records` - Audit trail of all consents
- `data_requests` - Track DSAR and erasure requests
- `data_breaches` - Breach notification log (GDPR 72-hour requirement)
- `compliance_settings` - Privacy configuration

---

## 🔧 Implementation Checklist

### Required Actions

- [ ] **Run Database Schema**
  ```sql
  -- For MySQL
  source backend/database/gdpr_schema.sql;
  
  -- For SQLite, use the SQLite-specific tables
  ```

- [ ] **Include Cookie Consent on All Pages**
  ```html
  <script src="/js/cookie-consent.js"></script>
  ```

- [ ] **Add Privacy Policy Link**
  Add to all page footers:
  ```html
  <a href="/privacy-policy.html">Privacy Policy</a>
  ```

- [ ] **Add Consent Checkboxes to Forms**
  Include in assessment and sales forms:
  ```html
  <!-- Include the consent component -->
  <?php include '../backend/components/consent-checkbox.html'; ?>
  ```

- [ ] **Configure DPO Email**
  Update in admin dashboard: Settings → Privacy Settings → DPO Email

- [ ] **Update Settings**
  Configure in admin dashboard:
  - Data retention period (default: 730 days)
  - Consent record retention (default: 1095 days / 3 years)
  - Cookie consent requirement
  - Marketing consent requirement

---

## 📊 Data Subject Rights Implementation

| Right | Implementation | Location |
|-------|---------------|----------|
| Right to Access | Export user data as JSON | Admin → GDPR/NDPR → Export Data |
| Right to Rectification | Update user data via admin | Admin → Users |
| Right to Erasure | Anonymize user records | Admin → GDPR/NDPR → Delete User Data |
| Right to Portability | JSON export download | Admin → GDPR/NDPR → Export Data |
| Right to Withdraw Consent | Cookie preferences modal | Cookie banner → Manage Preferences |
| Right to Object | Marketing opt-out | All emails include unsubscribe |

---

## 🔐 Sensitive Data Handling

### Health Data Categories
The system processes sensitive health data under **GDPR Article 9(2)(a)** - explicit consent:

1. **PCOS Assessment:**
   - Menstrual cycle information
   - Reproductive health symptoms
   - Hormonal imbalance indicators

2. **Weight Assessment:**
   - Body measurements
   - Dietary habits
   - Exercise patterns

3. **Acne Assessment:**
   - Skin condition details
   - Medical history
   - Treatment history

### Consent Requirements
- **Explicit consent** required before processing
- **Separate consent** for each processing purpose
- **Easy withdrawal** mechanism provided
- **Clear information** about data usage

---

## 📁 Data Retention Schedule

| Data Type | Retention Period | Legal Basis |
|-----------|-----------------|-------------|
| Active customer data | Plan duration + 2 years | Contract performance |
| Assessment data | 2 years from last interaction | Consent |
| Payment records | 7 years | Legal obligation (tax law) |
| Consent records | 3 years | NDPR audit requirement |
| Analytics data | Until consent withdrawn | Consent |
| Marketing data | Until consent withdrawn | Consent |

---

## 🚨 Data Breach Procedure

### 72-Hour Notification Requirement (GDPR Article 33)

1. **Discovery:** Log breach in `data_breaches` table
2. **Assessment:** Determine severity and affected users
3. **Authority Notification:** Report to NDPC/FCC within 72 hours
4. **User Notification:** Inform affected data subjects
5. **Remediation:** Document actions taken

**Admin Access:** Admin → GDPR/NDPR → Compliance Reports → Data Breach Log

---

## 📝 Compliance Reports

Generate reports from admin dashboard:

1. **Full Compliance Report** - Overview of all compliance metrics
2. **Consent Records Report** - Audit trail of consents
3. **Data Access Requests Report** - DSAR tracking
4. **Data Retention Report** - Records due for deletion
5. **Data Breach Log** - Breach history

---

## 🌍 Jurisdiction Notes

### GDPR (EU)
- Applies to EU residents' data
- Requires lawful basis for processing
- 72-hour breach notification
- Fines up to €20 million or 4% global turnover

### NDPR (Nigeria)
- Applies to Nigeria residents' data
- Requires consent for data processing
- Requires data protection officer
- Fines up to 2% annual revenue or ₦10 million

---

## 🔗 Integration Points

### Assessment Forms
Add consent before submit button:
```html
<!-- Before submit button -->
<?php include '../backend/components/consent-checkbox.html'; ?>
```

### Thank You Pages
Add privacy notice:
```html
<p class="text-sm text-gray-500">
    Your data is protected in accordance with GDPR and NDPR.
    <a href="/privacy-policy.html" class="text-green-600">Learn more</a>
</p>
```

### Email Templates
Include privacy notice and unsubscribe:
```html
<p style="font-size: 12px; color: #666;">
    We respect your privacy. 
    <a href="{{unsubscribe_url}}">Unsubscribe</a> | 
    <a href="{{privacy_policy_url}}">Privacy Policy</a>
</p>
```

---

## 📞 Data Protection Officer

**Contact:** dpo@ojgherbal.com

The DPO handles:
- Data subject rights requests
- Compliance monitoring
- Breach notifications
- Regulatory communications

---

## 📚 Additional Resources

- [GDPR Full Text](https://gdpr.eu/)
- [NDPR 2019 Full Text](https://ndpr.gov.ng/)
- [ICO Guide to GDPR](https://ico.org.uk/for-organisations/guide-to-data-protection/guide-to-the-general-data-protection-regulation-gdpr/)
- [NDPC Guidelines](https://ndpc.gov.ng/)

---

## ✅ Compliance Verification

Run these checks periodically:

1. **Cookie Consent:** Verify banner appears on first visit
2. **Privacy Policy:** Ensure link is accessible from all pages
3. **Consent Recording:** Check `consent_records` table is populated
4. **DSAR Processing:** Test data export function
5. **Data Deletion:** Verify anonymization works correctly
6. **Admin Access:** Confirm GDPR dashboard is accessible

---

**Last Updated:** January 2025  
**Version:** 1.0.0