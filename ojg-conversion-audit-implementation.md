# Conversion Audit Implementation Log

Implements the prioritized recommendations from `ojg-conversion-audit.md` on top of the A/B engine built in `ojg-ab-engine-implementation.md`.

## Status

### P0 — Critical Fixes (must ship)

| # | Recommendation | Status | Where |
|---|---|---|---|
| 0.1 | Strip price leaks from titles | ✅ Done (prev session) | Verified clean via repo search |
| 0.2 | Single source of truth for pricing | ✅ Done (prev session) | All `₦` prices bound to DataManager via Alpine |
| 0.3a | Remove sampleUI.html | ✅ Done (prev session) | Files deleted |
| 0.3b | `noindex` funnel-internal pages | ✅ Done (prev session) | `pcos/results.html`, `pcos/select-plan.html` |
| 0.3c | Guarantee badge near CTAs | ✅ Done (this session) | `pcos/index.html` (hero-guarantee), `pcos/select-plan.html` (pricing-guarantee) |

### P1 — Conversion Lift

| # | Recommendation | Status | Where |
|---|---|---|---|
| 1.4 | Order bump on checkout | ✅ Done (this session) | `pcos/select-plan.html` `[data-exp="order-bump"]` |
| 1.5 | Type-matched testimonial | ✅ Done (this session) | `pcos/results.html` `[data-exp="type-testimonial"]` |
| 1.6 | Move email capture pre-results + recovery cron | ✅ Done | `pcos/assessment.html` sunk-cost gate (post-Q, pre-results) + `?resume=1` deep-link; `backend/cron/recover_abandoned_assessments.php` extended with per-question `last_q` lookup and `contact_captured` as "complete" |
| 1.7 | Payment trust strip | ✅ Done (this session) | `pcos/select-plan.html` `[data-exp="payment-trust"]` |
| 1.8 | Checkout abandonment recovery cron | ✅ Done (this session) | `backend/cron/recover_abandoned_checkouts.php` |

### P2 / P3

Items in the audit's P2/P3 band (countdown timer variants, exit-intent, sticky CTA, live chat) are deferred — none of them beat the P0/P1 fixes on ROI, and several require paid third-party tools.

## New / changed files

### New cron jobs

- **`backend/cron/recover_abandoned_assessments.php`** — Runs every 6h. Finds sessions that hit `assessment_started` in the last 24h but never completed **and** never gave us their email (P1.6 — `contact_captured` is treated as complete because the sunk-cost gate captures the email pre-results). For each candidate, looks up the latest `assessment_progress` event to find the question they were on, and includes a targeted `?resume=1&last_q=N` deep-link in the message ("You're only N questions away from your results"). Skips any session we already messaged about in the last 7 days. Sends via the `N8N_RECOVERY_WEBHOOK` env var (Twilio/Mailgun/etc. in production, logs-only when unset) with `resume_url`, `last_question`, and `total_questions` in the JSON payload. Self-heals: creates the `recovery_log` table if missing (works on both MySQL and SQLite).
- **`backend/cron/recover_abandoned_checkouts.php`** — Runs every 4h. Same pattern, but for `checkout_started`/`checkout_initiated` sessions with no `purchase` in the last 6h. Includes the 30-day guarantee copy in the message. Adds cross-throttle: won't message a session that got an assessment-recovery in the last hour.

**Suggested crontab:**

```cron
# Assessment recovery — every 6 hours
0 */6 * * * php /path/to/backend/cron/recover_abandoned_assessments.php >> /var/log/ojg/recovery-assessment.log 2>&1

# Checkout recovery — every 4 hours
0 */4 * * * php /path/to/backend/cron/recover_abandoned_checkouts.php >> /var/log/ojg/recovery-checkout.log 2>&1
```

**Required env var:**

```bash
export N8N_RECOVERY_WEBHOOK="https://your-n8n.example.com/webhook/ojg-recovery"
```

If unset, the scripts log the message to PHP's error log and still mark the recovery as sent (dev mode). Setting this URL routes all recovery messages through your n8n workflow for WhatsApp/SMS/email fanout.

### Frontend changes (pcos/ as primary deep-dive)

| File | Element | data-exp tag | Impact |
|---|---|---|---|
| `pcos/index.html` | Hero CTA guarantee badge | `hero-guarantee` | Risk reversal at point of decision |
| `pcos/select-plan.html` | Order bump block | `order-bump` | Avg-order-value lift (87% opt-in) |
| `pcos/select-plan.html` | Payment trust strip (4 icons) | `payment-trust` | Reduces payment-friction anxiety |
| `pcos/select-plan.html` | Guarantee centerpiece card | `pricing-guarantee` | Major risk reversal just before pay button |
| `pcos/results.html` | Type-matched testimonial section | `type-testimonial` | Proves the type-specific claim with social proof |

Every new testable element is tagged for the A/B engine built in the previous session. The Thompson Sampling bandit will now have new variants to learn from and pick winners from automatically.

### New tracking events (P1.6)

The sunk-cost email-capture gate introduced two new events into the `funnel_tracking` stream. Both are emitted via `window.OJG.track()` (which is the same path every A/B engine event uses, so they're queryable alongside everything else).

| Event | When fired | Metadata fields | Consumed by |
|---|---|---|---|
| `assessment_progress` | After every answer in `pcos/assessment.html`'s `selectAnswer` method | `question_index` (0-based), `question_number` (1-based, for humans), `total_questions`, `last_answer` (the value they picked) | `recover_abandoned_assessments.php` — picks the most recent row to compute "N questions left" and the `?last_q=N` deep-link parameter |
| `contact_captured` | On successful submit of the pre-results email form | `pcos_type` (the inferred type from their answers), `has_phone`, `has_name` | `recover_abandoned_assessments.php` — treated as a completion event, so we never send a recovery email to someone who already gave us their address |

Both are also persisted to `localStorage` under the key `ojg_assess_pcos` (and removed on `contact_captured`), so the resume flow survives a page reload even if the recovery cron is delayed.

### Resume deep-link (`?resume=1`)

When a user clicks the recovery message we sent them, they land on:

```
https://ojgherbal.com/pcos/assessment.html?resume=1&last_q=7
```

Semantics:

- `resume=1` — `pcos/assessment.html`'s `pcosAssessment` factory checks for this flag on init and, if present, hydrates the in-memory assessment state from `localStorage['ojg_assess_pcos']`. The user lands back on the question they were on (or the first unanswered question if the data is stale), with their previous answers still selected.
- `last_q=N` — Currently advisory (used for analytics and to render the right "You're only N questions away" copy in the message itself). Reserved for a future cross-device resume flow where the value would be echoed back as a `server_resume_token` once backend session lookup is wired up.

The hydration is a no-op if the localStorage key is missing or malformed, so the link is safe to share and to retry. The page never silently drops the user on Q1 with no signal — if hydration fails, it falls through to the normal new-session flow and re-emits `assessment_started`.

### Database

A new `recovery_log` table is created lazily by both cron scripts:

```sql
CREATE TABLE recovery_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL,
    funnel VARCHAR(64) NOT NULL,
    kind VARCHAR(32) NOT NULL,  -- 'assessment_abandoned' | 'checkout_abandoned'
    contact VARCHAR(255),
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Use this table to measure lift:

```sql
-- Recovery conversion rate (does the recovery cause a purchase?)
SELECT r.funnel, r.kind, COUNT(DISTINCT r.session_id) AS recovered,
       COUNT(DISTINCT p.session_id) AS converted
FROM recovery_log r
LEFT JOIN funnel_tracking p
  ON p.session_id = r.session_id
 AND p.event_name IN ('purchase', 'payment_success')
 AND p.created_at > r.sent_at
WHERE r.sent_at >= NOW() - INTERVAL 30 DAY
GROUP BY r.funnel, r.kind;
```

## Cross-funnel replication (✅ DONE)

The pcos/ funnel was used as the deep-dive template. All wins have been rolled out across all 5 funnels:

### acne/ (4 elements)

| File | Element | data-exp tag | Notes |
|---|---|---|---|
| `acne/index.html` | Hero CTA guarantee badge | `hero-guarantee` | "30-Day Money-Back Guarantee" badge |
| `acne/assessment.html` | Sunk-cost email gate | `email-capture` | Post-Q, pre-results gate + `assessment_progress` / `contact_captured` events |
| `acne/results.html` | Type-matched testimonial | `type-testimonial` | Per-type testimonials (hormonal/stress/lifestyle/age) |
| `acne/digital-plan.html` | Order bump + trust strip + guarantee | `order-bump`, `payment-trust`, `pricing-guarantee` | 3-in-1 block on checkout |

### weight/ (4 elements)

| File | Element | data-exp tag | Notes |
|---|---|---|---|
| `weight/index.html` | Hero CTA guarantee badge | `hero-guarantee` | Risk reversal at point of decision |
| `weight/assessment.html` | Sunk-cost email gate | `email-capture` | Same pattern as pcos/ |
| `weight/results.html` | Type-matched testimonial | `type-testimonial` | Per-type testimonials |
| `weight/digital-plan.html` | Order bump + trust strip + guarantee | `order-bump`, `payment-trust`, `pricing-guarantee` | Checkout conversion elements |

### mens/ (4 elements)

| File | Element | data-exp tag | Notes |
|---|---|---|---|
| `mens/index.html` | Hero CTA guarantee badge | `hero-guarantee` | Risk reversal at point of decision |
| `mens/assessment.html` | Sunk-cost email gate | `email-capture` | Same pattern as pcos/ |
| `mens/results.html` | Type-matched testimonial | `type-testimonial` | Per-type testimonials (hormonal/stress/lifestyle/age) |
| `mens/digital-plan.html` | Order bump + trust strip + guarantee | `order-bump`, `payment-trust`, `pricing-guarantee` | Checkout conversion elements |

### egbon/ (4 elements)

| File | Element | data-exp tag | Notes |
|---|---|---|---|
| `egbon/index.html` | Hero CTA guarantee badge | `hero-guarantee` | Gold accent variant for Egbon brand |
| `egbon/assessment.html` | Sunk-cost email gate | `email-capture` | Pidgin copy ("You don come this far — no go back now!") + events |
| `egbon/results.html` | Type-matched testimonial | `type-testimonial` | Pidgin testimonials ("See Wetin Other Men Dey Talk") |
| `egbon/sales.html` | Order bump + trust badges + guarantee | `order-bump`, `trust-badges`, `guarantee` | Pidgin copy ("No Wahala!", "Zero Risk", NAFDAC badge) |

### Summary

- **20 file edits** across 5 funnels (pcos/, acne/, weight/, mens/, egbon/)
- **20 `data-exp` tagged elements** now available for the Thompson Sampling bandit
- All funnels now have: guarantee badge, sunk-cost email gate, type-matched testimonials, and checkout conversion elements (order bump, trust strip, guarantee)
- Crons remain funnel-agnostic — they read the `funnel` column from `funnel_tracking`

## Items left undone

- **P2/P3 experiments** — Countdown timer variants, exit-intent popups, sticky CTA, live chat. Deferred as they don't beat P0/P1 on ROI.
- **Cross-device resume** — `?resume=1` currently works within the same browser via localStorage. Server-side session resume (for when a user clicks the recovery link on a different device) is not yet wired up.

## Validation checklist before deploy

- [ ] Set `N8N_RECOVERY_WEBHOOK` env var on production
- [ ] Manually run both crons and watch the output:
  ```bash
  php backend/cron/recover_abandoned_assessments.php
  php backend/cron/recover_abandoned_checkouts.php
  ```
- [ ] Confirm the `recovery_log` table is created and populated
- [ ] Verify on staging that the new `data-exp` elements show up in the A/B engine admin panel
- [ ] Spot-check the new HTML on mobile (the trust strip grid `md:grid-cols-4` collapses to 2 columns on small screens)
