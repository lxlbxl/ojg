# OJG A/B Testing Engine — Setup & Operator Guide

## What it is

A self-contained Thompson Sampling bandit engine that runs A/B and multivariate
tests across the 5 production funnels (pcos, acne, weight, mens, egbon). It
exposes two testing surfaces:

1. **Element-level** — any HTML element can be tagged with `data-exp="exp_key"`
   and have its text/HTML/attribute/style/config overridden per variant.
2. **Structural** — sibling directories named `{funnel}__{slug}/` (e.g.
   `pcos__b/`) are treated as a different layout of the parent funnel.

## Architecture

```
Browser                    Apache / .htaccess
  |                              |
  |  GET /pcos/                  |
  |----------------------------->|
  |                              | router.php
  |                              | -> emit 'view' event
  |                              | -> set ojg_sid cookie if missing
  |<-----------------------------|
  | <html> with bootstrap script |
  |                              |
  |  OJG.track('view', {...})    |
  |----------------------------->|
                         track-event.php -> experiment_events + funnel_tracking
```

Server-side events (purchases) are emitted by `webhook_purchase.php` after
Flutterwave signature verification, with the actual revenue captured.

## Files

| File | Role |
|---|---|
| `backend/database/migrations/001_ab_engine_mysql.sql` | MySQL schema (8 tables + funnel_tracking ALTER) |
| `backend/database/migrations/001_ab_engine_sqlite.sql` | SQLite mirror |
| `backend/classes/Bandit.php` | Thompson Sampling math |
| `backend/classes/ExperimentRepository.php` | All DB access for the engine |
| `backend/classes/AssignmentService.php` | Sticky variant assignment + cookie helpers |
| `backend/classes/ExperimentTracker.php` | Validated event ingestion |
| `backend/api/track-event.php` | Client-facing event endpoint |
| `backend/api/webhook_purchase.php` | Unified Flutterwave purchase webhook |
| `backend/cron/recompute_posteriors.php` | 15-min rollup + winner detection |
| `backend/cron/ai_diagnostics.php` | Weekly AI insights |
| `backend/cron/challenger_generator.php` | Weekly AI challenger drafting |
| `backend/router.php` | Front controller (variant routing + view tracking) |
| `.htaccess` | URL rewrites for structural variants + router |
| `js/exp-overrides.js` | DOM applier for `window.__VARIANT_OVERRIDES` |
| `backend/admin/index.php` | Admin SPA — Experiments view added |
| `backend/admin/js/admin.js` | Admin SPA controller — experiments case added |

## Auto-migration

The engine runs its own migrations on every `Database::getInstance()` call via
`ensureExperimentColumns()`. The MySQL/SQLite split is automatic. Re-running
is safe — the runner ignores error codes 1050, 1060, 1061, 1062
(table/column/index already exists).

To force a re-run, drop the affected tables manually; the next request will
recreate them.

## Setting up the first experiment

1. Sign into the admin panel (`/backend/admin/` or `/admin/`).
2. Click **Experiments** in the sidebar.
3. Click **+ New experiment**.
4. Fill in:
   - Name (e.g. "PCOS hero urgency angle")
   - Funnel (`pcos`)
   - Stage (`landing`)
   - Reward type (`conversion` for binary, `revenue` for AOV-weighted)
   - Min samples per variant (default 50)
5. Add at least 2 variants. For each:
   - Slug (e.g. `control`, `urgency`)
   - Type (`element` for overrides, `structural` for `{funnel}__{slug}/`)
   - Overrides JSON (see schema below)
6. Click **Start**.

The engine will immediately start assigning visitors 50/50 (or per
`traffic_weight`) with a uniform random burn-in until each variant hits
`min_samples_per_variant`. After that, Thompson Sampling takes over.

## Override JSON schema

```json
{
  "headline": {
    "type": "text",
    "selector": "h1.hero-title",
    "value": "Finally, a plan that adapts to your cycle."
  },
  "cta_color": {
    "type": "style",
    "selector": "a.hero-cta",
    "value": { "backgroundColor": "#10b981" }
  },
  "trust_badge": {
    "type": "html",
    "selector": "div.trust-row",
    "value": "<strong>4.8/5</strong> from 2,300+ women"
  },
  "price_override": {
    "type": "config",
    "key": "pricing.pcos-90-day-plan",
    "value": { "currency": "USD", "original": 197, "sale": 97 }
  }
}
```

## Tagging HTML

Add `data-exp="headline"` to any element you want to override. The applier
patches it as soon as the bootstrap script defines the matching key in
`__VARIANT_OVERRIDES`.

## Cron schedule (suggested)

```cron
*/15 * * * *  php /home/ojg/backend/cron/recompute_posteriors.php
0 6 * * 1    php /home/ojg/backend/cron/ai_diagnostics.php
0 8 * * 1    php /home/ojg/backend/cron/challenger_generator.php
```

## Capacity rules

- Concurrent experiments per funnel/stage: **1 active** (other rows are queued)
- Max variants per experiment: **6**
- Min samples per variant before Thompson engages: **50** (configurable)
- Reward: `conversion` (Beta) or `revenue` (Beta × empirical AOV)
- Auto-complete when confidence ≥ 0.95

## GDPR

- `view` and other non-essential events are gated on `analytics` consent
  (see `js/cookie-consent.js`).
- `purchase` events are always recorded server-side (essential for
  fulfilment) but metadata is minimised.
- The admin can purge a session's events with one click.

## Pre-launch checklist

- [ ] MySQL or SQLite is configured in `backend/database/settings.json`
- [ ] `flutterwave_webhook_secret` is set in Settings
- [ ] Cron entries are installed
- [ ] Admin password changed from `admin/admin123`
- [ ] At least one experiment created in the admin
- [ ] Funnel pages load with `window.__VARIANT_OVERRIDES` populated
- [ ] Anti-flicker works (html opacity released within 300ms)
- [ ] A test purchase fires a `purchase` event with revenue in the admin
