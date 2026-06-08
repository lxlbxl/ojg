# OJG Herbal Codebase Holistic Review

**Date:** June 2026  
**Purpose:** Analyze codebase structure, connections, and identify deprecated/unnecessary files

---

## Executive Summary

This review analyzes the OJG Herbal Health Assessment System, covering:
- Member Area
- Admin Backend
- Funnels (PCOS, Weight, Egbon, Acne)
- Currency variants (OJG-NGN, OJG-USD)

---

## 1. Project Structure Overview

```
OJG-Herbal-Updated/
├── backend/              # Main PHP Backend & Admin (NGN)
├── member/               # Member Area (NGN)
├── pcos/                 # PCOS Funnel (NGN)
├── acne/                 # Acne Funnel (NGN)
├── weight/               # Weight Loss Funnel (NGN)
├── egbon/                # Egbon Funnel (NGN)
├── mens/                 # Men's Vitality Funnel (NGN)
├── js/                   # Shared JavaScript libraries
├── images/               # Shared images
├── frontend/             # React/Vite Frontend (unused?)
├── ojg-ngn/              # Nigeria deployment mirror (NGN currency)
├── ojg-usd/              # US deployment mirror (USD currency)
└── [build scripts]       # Development/build tools
```

---

## 2. Component Analysis

### 2.1 Backend (`/backend/`) - **CORE COMPONENT**

**Purpose:** Central API, admin panel, database, AI orchestration

**Key Files:**
| File/Folder | Purpose | Status |
|-------------|---------|--------|
| `backend/index.php` | API entry point | ✅ Required |
| `backend/admin/` | Admin dashboard | ✅ Required |
| `backend/api/` | REST API endpoints | ✅ Required |
| `backend/database/` | SQLite/MySQL database | ✅ Required |
| `backend/classes/` | PHP classes | ✅ Required |
| `backend/config/` | Configuration | ✅ Required |
| `backend/components/` | Reusable UI components | ✅ Required |
| `backend/templates/` | Email templates | ✅ Required |
| `backend/cron/` | Scheduled tasks | ✅ Required |
| `backend/prompts/` | AI prompts | ✅ Required |

**Debug Files (Development Only):**
| File | Can Delete? | Notes |
|------|-------------|-------|
| `backend/debug_admin.php` | ✅ Yes | Development debugging |
| `backend/debug_ai.php` | ✅ Yes | Development debugging |
| `backend/debug_api.php` | ✅ Yes | Development debugging |
| `backend/debug_settings.php` | ✅ Yes | Development debugging |
| `backend/debug_log.txt` | ✅ Yes | Auto-generated log |
| `backend/fix_db_error.php` | ✅ Yes | One-time fix script |
| `backend/fix_metadata_column.php` | ✅ Yes | One-time fix script |
| `backend/update_schema_tracking.php` | ✅ Yes | One-time migration |
| `backend/migrate_activity_logs.php` | ✅ Yes | One-time migration |
| `backend/migrate_leads_tracking.php` | ✅ Yes | One-time migration |
| `backend/test_login_check.php` | ✅ Yes | Testing only |
| `backend/test_onboarding.php` | ✅ Yes | Testing only |
| `backend/simulate_login.php` | ✅ Yes | Testing only |
| `backend/verify_fix.php` | ✅ Yes | Testing only |
| `backend/create_test_user.php` | ⚠️ Optional | Keep for testing new admins |
| `backend/create_admin.php` | ⚠️ Optional | Keep for creating new admins |

**Archived Folder:**
- `backend/_archived_node-service/` - ✅ **DELETE** - Old Node.js service, no longer used

---

### 2.2 Member Area (`/member/`) - **CORE COMPONENT**

**Purpose:** Authenticated member dashboard with personalized plans

**Key Files:**
| File/Folder | Purpose | Status |
|-------------|---------|--------|
| `member/index.php` | Member dashboard entry | ✅ Required |
| `member/login.php` | Member login | ✅ Required |
| `member/api/` | Member API endpoints | ✅ Required |
| `member/components/` | Member UI components | ✅ Required |
| `member/css/` | Member styles | ✅ Required |
| `member/js/` | Member JavaScript | ✅ Required |
| `member/print_plan.php` | Print personalized plan | ✅ Required |

**HTML Fallback Files:**
| File | Can Delete? | Notes |
|------|-------------|-------|
| `member/index.html` | ⚠️ Check | May be fallback, verify if used |
| `member/login.html` | ⚠️ Check | May be fallback, verify if used |

---

### 2.3 Funnels - **CORE COMPONENTS**

Each funnel is a standalone lead capture & sales system:

#### PCOS Funnel (`/pcos/`)
| File | Purpose | Status |
|------|---------|--------|
| `index.html` | Landing page | ✅ Required |
| `assessment.html` | Health assessment | ✅ Required |
| `results.html` | Results display | ✅ Required |
| `thank-you.html` | Post-purchase thank you | ✅ Required |
| `30-day-plan.html` | 30-day plan content | ✅ Required |
| `90-day-plan.html` | 90-day plan content | ✅ Required |
| `digital-plan.html` | Digital plan content | ✅ Required |
| `generating-plan.html` | Plan generation loading | ✅ Required |
| `sampleUI.html` | ⚠️ **DELETE** - Development sample |
| `old/` | ⚠️ **DELETE** - Old versions |
| `old-sales-page.html` | ⚠️ **DELETE** - Deprecated sales page |

#### Acne Funnel (`/acne/`)
| File | Purpose | Status |
|------|---------|--------|
| `index.html` | Landing page | ✅ Required |
| `assessment.html` | Health assessment | ✅ Required |
| `results.html` | Results display | ✅ Required |
| `thank-you.html` | Post-purchase thank you | ✅ Required |
| `old-sales-page.html` | ⚠️ **DELETE** - Deprecated |

#### Weight Funnel (`/weight/`)
| File | Purpose | Status |
|------|---------|--------|
| `index.html` | Landing page | ✅ Required |
| `assessment.html` | Health assessment | ✅ Required |
| `results.html` | Results display | ✅ Required |
| `thank-you.html` | Post-purchase thank you | ✅ Required |

#### Egbon Funnel (`/egbon/`)
| File | Purpose | Status |
|------|---------|--------|
| `index.html` | Landing page | ✅ Required |
| `assessment.html` | Health assessment | ✅ Required |
| `results.html` | Results display | ✅ Required |
| `thank-you.html` | Post-purchase thank you | ✅ Required |
| `sales.html` | Sales page | ✅ Required |
| `README.md` | ⚠️ **DELETE** - Development notes |

#### Men's Funnel (`/mens/`)
| File | Purpose | Status |
|------|---------|--------|
| `index.html` | Landing page | ✅ Required |
| `assessment.html` | Health assessment | ✅ Required |
| `results.html` | Results display | ✅ Required |
| `thank-you.html` | Post-purchase thank you | ✅ Required |

---

### 2.4 Shared JavaScript (`/js/`) - **CORE COMPONENT**

| File | Purpose | Status |
|------|---------|--------|
| `config.js` | Global configuration | ✅ Required |
| `currency.js` | Currency conversion | ✅ Required |
| `tracking.js` | Analytics tracking | ✅ Required |
| `webhook-manager.js` | Webhook handling | ✅ Required |
| `data-manager.js` | Data management | ✅ Required |
| `flutterwave-integration.js` | Payment processing | ✅ Required |
| `admin-integration.js` | Admin panel scripts | ✅ Required |
| `TRACKING.md` | Documentation | ✅ Keep |

---

### 2.5 Frontend (`/frontend/`) - **LIKELY UNUSED**

This appears to be a React/Vite build that may not be integrated:

| Status | Action |
|--------|--------|
| ⚠️ **INVESTIGATE** | Check if this is used in production |

**Files present:**
- `vite.config.ts`, `tailwind.config.js`, `package.json`
- `src/`, `public/` directories

**Recommendation:** If not used, delete entire `/frontend/` folder

---

### 2.6 OJG-NGN (`/ojg-ngn/`) - **MIRROR DEPLOYMENT**

This is a complete mirror of the main deployment for Nigeria (NGN currency):

**Structure:**
```
ojg-ngn/
├── backend/    (mirror of /backend/)
├── member/     (mirror of /member/)
├── pcos/       (mirror of /pcos/)
├── acne/       (mirror of /acne/)
├── weight/     (mirror of /weight/)
├── egbon/      (mirror of /egbon/)
├── mens/       (mirror of /mens/)
└── js/         (mirror of /js/)
```

**Status:** ⚠️ **REDUNDANT** - This is a complete duplicate. Consider:
- Keep if deploying to separate server
- Delete if using main root deployment

---

### 2.7 OJG-USD (`/ojg-usd/`) - **SEPARATE DEPLOYMENT**

This is the US Dollar deployment variant:

**Key Differences from NGN:**
- Currency: USD instead of NGN
- Pricing: Different price points
- Payment: May use different gateway

**Structure:**
```
ojg-usd/
├── index.html          # Root landing
├── backend/            # USD-configured backend
├── member/             # Member area
├── pcos/               # PCOS funnel (USD)
├── acne/               # Acne funnel (USD)
├── weight/             # Weight funnel (USD)
├── mens/               # Men's funnel (USD)
├── js/                 # JS with USD config
└── images/             # Images
```

**Status:** ✅ **REQUIRED** - Separate market deployment

---

## 3. Component Connections

### Connection Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    USER FLOW                                 │
└─────────────────────────────────────────────────────────────┘

FUNNELS (pcos/acne/weight/egbon/mens)
         │
         │ Assessment → Contact Info → Payment
         ▼
    backend/api/
         │
         │ Creates: lead record, assessment data
         │ Triggers: AI plan generation
         ▼
    backend/database/
         │
         │ Stores: users, assessments, payments
         ▼
    member/
         │
         │ User logs in → Access personalized plan
         ▼
    backend/admin/
         │
         │ Admin views: leads, sales, members
```

### Database Connections

All components connect to the same database:
- **SQLite:** `backend/database/ojg_herbal.db` or `ojg_herbal.sqlite`
- **MySQL:** Via `backend/config/db_config.php` (if configured)

### API Endpoints Used by Funnels

| Endpoint | Purpose | Used By |
|----------|---------|---------|
| `backend/api/submit-assessment.php` | Submit assessment | All funnels |
| `backend/api/record-consent.php` | GDPR consent | All funnels |
| `backend/api/create-payment.php` | Create payment | Sales pages |
| `backend/api/verify-payment.php` | Verify payment | Thank-you pages |
| `backend/api/generate-plan.php` | AI plan generation | Results page |

---

## 4. Standalone vs Connected Components

### Standalone (Can Run Independently)

| Component | Dependencies | Notes |
|-----------|--------------|-------|
| `/pcos/` | backend/, js/ | Can be deployed alone |
| `/acne/` | backend/, js/ | Can be deployed alone |
| `/weight/` | backend/, js/ | Can be deployed alone |
| `/egbon/` | backend/, js/ | Can be deployed alone |
| `/mens/` | backend/, js/ | Can be deployed alone |
| `/ojg-usd/` | None | Complete standalone deployment |

### Connected (Requires Other Components)

| Component | Requires | Notes |
|-----------|----------|-------|
| `/member/` | backend/ | Needs API for auth |
| `/backend/` | None | Core component |
| `/js/` | None | Shared library |

---

## 5. Files/Folders to Delete

### Development/Build Scripts (Root)

| File | Reason |
|------|--------|
| `build_ojg.py` | Build script, not needed for runtime |
| `build_pcos.py` | Build script, not needed for runtime |
| `build.js` | Build script, not needed for runtime |
| `gen.js` | Build script, not needed for runtime |
| `create_*.py` | File generation scripts |
| `create_all.py` | File generation scripts |
| `create_pages.py` | File generation scripts |
| `create_pcos_pages.py` | File generation scripts |
| `create_files.py` | File generation scripts |
| `write_files.py` | File generation scripts |
| `script.py` | Unknown script |
| `test.py` | Test script |
| `test_script.py` | Test script |
| `temp_script.py` | Temporary script |
| `start-all.js` | Development runner |
| `server.js` | Development server |
| `check_schema.php` | Development utility |
| `fix_schema.php` | One-time fix |
| `fix_tools_schema.php` | One-time fix |
| `tmp_*.php` | Temporary files |
| `debug_*.txt` | Debug logs |
| `debug_api.txt` | Debug logs |
| `debug_log.txt` | Debug logs |
| `debug_tools.php` | Debug utility |
| `test_all_*.php` | Test scripts |
| `verify_*.php` | Verification scripts |
| `update_*.php` | Update scripts |
| `reset_*.php` | Reset scripts |
| `get_test_users.php` | Testing utility |
| `nul` | Empty file |
| `dartsdk.zip` | ⚠️ Check if needed |
| `backend.zip` | Backup, can delete |
| `3folds.zip` | ⚠️ Check if needed |
| `mysql-tester/` | Development testing |

### Documentation (Keep or Delete Based on Need)

| File | Recommendation |
|------|----------------|
| `AI_ENGINE_MANUAL.md` | ✅ Keep - Operations |
| `AUTO_DISCOVERY_GUIDE.md` | ✅ Keep - Documentation |
| `DEPLOYMENT_GUIDE.md` | ✅ Keep - Deployment |
| `INSTALLATION.md` | ✅ Keep - Installation |
| `PRICING_SYSTEM.md` | ✅ Keep - Reference |
| `QUICK_DEPLOY_CHECKLIST.md` | ✅ Keep - Deployment |
| `FUNNEL_AUDIT.md` | ✅ Keep - Reference |
| `MEMBER_AREA_REVIEW.md` | ✅ Keep - Reference |
| `KPI_DASHBOARD.md` | ✅ Keep - Operations |
| `LTV_ANALYSIS.md` | ✅ Keep - Analytics |
| `CFO_REPORT.md` | ⚠️ Archive old reports |
| `STAKEHOLDER_SUMMARY.md` | ⚠️ Archive old reports |
| `walkthrough.md` | ✅ Keep - Documentation |
| `OJG_Brand_Universe_Guidelines.md` | ✅ Keep - Brand |
| `COMPLIANCE_GUIDE.md` | ✅ Keep - Legal |
| `90-day-plan.html` | ⚠️ Check if used |
| `30-day-plan.html` | ⚠️ Check if used |
| `digital-plan.html` | ⚠️ Check if used |
| `assessment.html` (root) | ⚠️ Check if used |
| `results.html` (root) | ⚠️ Check if used |
| `thank-you.html` (root) | ⚠️ Check if used |
| `index.html` (root) | ⚠️ Check if used |

### Backend Debug/Migration Files

| File | Reason |
|------|--------|
| `backend/_archived_node-service/` | Old service, delete |
| `backend/debug_*.php` | Debug utilities |
| `backend/fix_*.php` | One-time fixes |
| `backend/migrate_*.php` | Completed migrations |
| `backend/update_*.php` | Update scripts |
| `backend/test_*.php` | Test files |
| `backend/verify_*.php` | Verification |
| `backend/simulate_login.php` | Testing |
| `backend/create_test_user.php` | Keep optional |
| `backend/debug_log.txt` | Auto-generated log |

### Funnel Deprecated Files

| File | Location | Reason |
|------|----------|--------|
| `sampleUI.html` | `/pcos/` | Development sample |
| `old/` | `/pcos/` | Old versions |
| `old-sales-page.html` | `/pcos/`, `/acne/` | Deprecated |
| `README.md` | `/egbon/` | Development notes |

---

## 6. Production-Ready Structure (Recommended)

After cleanup, the production deployment should look like:

```
OJG-Herbal-Production/
├── backend/                  # Core backend
│   ├── index.php
│   ├── admin/
│   ├── api/
│   ├── classes/
│   ├── components/
│   ├── config/
│   ├── cron/
│   ├── database/
│   │   ├── ojg_herbal.sqlite
│   │   ├── schema.sql
│   │   └── settings.json
│   ├── prompts/
│   └── templates/
├── member/                   # Member area
├── pcos/                     # PCOS funnel
├── acne/                     # Acne funnel
├── weight/                   # Weight funnel
├── egbon/                    # Egbon funnel
├── mens/                     # Men's funnel
├── js/                       # Shared JS
├── images/                   # Shared images
├── privacy-policy.html       # Legal
├── index.html               # Optional root landing
└── .htaccess                # Apache config (if using Apache)
```

---

## 7. Deployment Variants

### Variant A: Single Deployment (Recommended)
Use root directory for NGN market, separate domain/subdomain for USD

### Variant B: Dual Deployment
- `/ojg-ngn/` → Nigeria (NGN)
- `/ojg-usd/` → International (USD)

### Variant C: Standalone Funnels
Deploy individual funnels with shared backend

---

## 8. GDPR/NDPR Compliance Files

Recently added compliance files (June 2026):

| File | Status | Purpose |
|------|--------|---------|
| `privacy-policy.html` | ✅ Required | Legal requirement |
| `js/cookie-consent.js` | ✅ Required | Cookie consent |
| `backend/api/record-consent.php` | ✅ Required | Consent API |
| `backend/admin/gdpr-ndpr.php` | ✅ Required | Compliance dashboard |
| `backend/components/consent-checkbox.html` | ✅ Required | Form component |
| `backend/database/gdpr_schema.sql` | ✅ Required | DB schema |
| `backend/database/run_gdpr_schema.php` | ⚠️ Delete after running | One-time installer |
| `COMPLIANCE_GUIDE.md` | ✅ Keep | Documentation |

---

## 9. Summary Actions

### Immediate Actions

1. **Run GDPR Schema:**
   ```
   Visit: http://yourdomain.com/backend/database/run_gdpr_schema.php
   Then delete: backend/database/run_gdpr_schema.php
   ```

2. **Delete Development Files:**
   ```bash
   # Build scripts
   del build_*.py build.js gen.js create_*.py write_files.py
   del script.py test.py test_script.py temp_script.py
   del start-all.js server.js
   
   # Debug files
   del debug_*.txt debug_*.php
   del backend/debug_*.php backend/fix_*.php
   del backend/migrate_*.php backend/test_*.php
   del backend/verify_*.php backend/simulate_*.php
   
   # Archived
   rmdir /s backend/_archived_node-service
   ```

3. **Delete Funnel Deprecated Files:**
   ```bash
   del pcos\sampleUI.html
   rmdir /s pcos\old
   del pcos\old-sales-page.html acne\old-sales-page.html
   del egbon\README.md
   ```

4. **Decide on Mirror Deployments:**
   - Keep `/ojg-usd/` for US market
   - Decide on `/ojg-ngn/` (redundant with root)
   - Delete `/frontend/` if not used

---

## 10. Connection Summary

| Component | Connects To | Purpose |
|-----------|-------------|---------|
| Funnels | backend/api/ | Submit assessments, payments |
| Member Area | backend/api/ | Authentication, plan access |
| Backend | database/ | Data storage |
| Admin | backend/, database/ | Management interface |
| ojg-usd | None (standalone) | Separate market deployment |
| ojg-ngn | None (mirror) | Mirror of root deployment |

---

**End of Review**