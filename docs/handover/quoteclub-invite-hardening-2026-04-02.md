# Quote Club Invite Hardening — Work Summary
**Date:** 2 April 2026
**Plugin:** `khm-plugin`
**Scope:** Quote Club sponsor invite acceptance — UX, backend hardening, E2E tests, CI gate

---

## What Was Done

### 1. Inline Invite Status UX (replaced `alert()`)

**Problem:** Invite acceptance success and failure were communicated with browser `alert()` dialogs — inaccessible, unbranded, and untestable.

**Solution:** Replaced all alerts with an inline status card wired to an ARIA live region.

Files modified:
- `assets/css/quote-club.css` — Added `.khm-quoteclub-invite-status` base styles plus state modifiers: `.is-pending` (blue), `.is-success` (green), `.is-error` (red). Also `.khm-invite-retry-btn`.
- `assets/js/quote-club.js` — New module-level state vars (`inviteRequestInFlight`, `inviteRetryPayload`), helper functions `isTransientInviteError()`, `renderInviteStatus()`, `acceptInvite()`. Retry click handler on `.khm-invite-retry-btn`. Refactored `maybeAcceptInviteFromUrl()` to delegate to `acceptInvite()`.
- `src/PublicFrontend/MemberPortalShortcode.php` — Added `<div class="khm-quoteclub-invite-status" role="status" aria-live="polite"></div>` inside `render_quoteclub_tab()`.

Behaviour:
- **Pending:** spinner label while request is in flight; in-flight guard prevents double-submit.
- **Success:** green card, URL invite params cleared from browser history.
- **Transient error** (HTTP 0, 409, 429, 5xx, `invite_in_progress`): red card with a **Retry** button that re-fires the same payload.
- **Hard error** (4xx other than 409/429): red card, no retry.

---

### 2. Idempotency Lock

**Problem:** A user clicking the accept link twice in rapid succession could trigger duplicate team member inserts.

**Solution:** WordPress transient lock around the accept operation.

File modified: `src/Rest/QuoteClubController.php`

- On entry: `get_transient($lock_key)` — if set, return HTTP 409 `invite_in_progress`.
- On entry: `set_transient($lock_key, 1, MINUTE_IN_SECONDS)`.
- Entire accept body wrapped in `try { … } finally { delete_transient($lock_key); }` — lock is always released even if an exception is thrown.
- Lock key: `khm_qc_invite_accept_lock_` + MD5 of the invite token (`invite_accept_lock_key()` private helper).

---

### 3. Telemetry Action

**Problem:** No observable event emitted on successful invite acceptance — no way to hook analytics, logs, or downstream workflows.

**Solution:** WordPress action hook fired on success.

Files modified:
- `src/Rest/QuoteClubController.php` — `do_action('khm_quoteclub_invite_accepted', ['user_id'=>…, 'sponsor_id'=>…, 'email'=>…, 'accepted_at'=>…])` fired immediately before returning HTTP 200.
- `khm-plugin.php` — `add_action('khm_quoteclub_invite_accepted', …)` operational log listener: writes `[KHM Quote Club] invite_accepted sponsor_id=%d user_id=%d email=%s` to the error log.

---

### 4. PHPUnit Baseline + New Tests

**Baseline captured:** **154 tests, 628 assertions, 0 failures** (PHP 8.5.4 / PHPUnit 9.6.34).

New tests added to `tests/Rest/QuoteClubControllerTest.php`:
- **Telemetry fires once on success** — filters `$GLOBALS['khm_test_actions_fired']` for hook `khm_quoteclub_invite_accepted`, asserts count = 1 with correct payload.
- **409 returned when lock is already active** — pre-sets the transient, calls accept, asserts status 409 and error code `invite_in_progress`.

Bootstrap change (`tests/bootstrap.php`): `do_action()` stub now records all fired actions to `$GLOBALS['khm_test_actions_fired']` (array of `{hook, args}`) so tests can assert telemetry.

---

### 5. Playwright E2E Browser Tests

**No WordPress required.** Tests run against a standalone HTML harness.

Files created:
- `tests/UI/quoteclub_harness.html` — Full `.khm-quoteclub` DOM matching production shortcode output. Reads `khm_sponsor_invite` / `khm_sponsor_invite_email` from URL params into `window.khmQuoteClub`. Loads real CSS + JS + jQuery from `node_modules`.
- `tests/UI/playwright.quoteclub.config.js` — Dedicated Playwright config. Spins up `python3 -m http.server 8080` serving the plugin root. Base URL `http://127.0.0.1:8080`.

Files modified:
- `tests/UI/quoteclub_invite_flow.spec.js` — Two deterministic tests using `page.route()` to mock the API:
  1. **Happy path** — mock returns 200, asserts success card text, asserts URL params cleared.
  2. **Retry path** — first call returns 500 `invite_in_progress`, asserts error card + retry button visible; on retry click, second call returns 200, asserts success card.
- `tests/UI/package.json` — Added `test:quoteclub` npm script and `jquery: ^3.7.1` devDependency.

**Local run command:**
```bash
cd app/public/wp-content/plugins/khm-plugin/tests/UI
npm install
npx playwright install --with-deps chromium
npm run test:quoteclub
# Expected: 2 passed (≈1.5s)
```

---

### 6. CI Gate

**CI job added:** `quoteclub-invite-ui` in `.github/workflows/mem-06-qa.yml`.

Job steps:
1. `actions/checkout@v4`
2. `actions/setup-node@v4` (Node 20)
3. `npm install && npx playwright install --with-deps chromium`
4. `npm run test:quoteclub`
5. Upload `playwright-report` as artifact `mem06-quoteclub-invite-report` (always, for failure diagnosis)

The job has **no `continue-on-error`** — it is merge-blocking.

---

### 7. Branch Protection Documentation

`quoteclub-invite-ui` recorded as a required branch protection status check in:
- `docs/membership/mem06_testing_qa_runbook.md` — new Quote Club browser gate section with run command, expected output, and CI job name.
- `docs/RELEASE_RUNBOOK.md` — added to Preflight checklist as step 3.

---

## Files Changed (full list)

| File | Change |
|---|---|
| `assets/css/quote-club.css` | Added invite status card styles |
| `assets/js/quote-club.js` | Replaced alerts with inline status UX; added idempotency guard, retry handler |
| `src/PublicFrontend/MemberPortalShortcode.php` | Added ARIA live status region div |
| `src/Rest/QuoteClubController.php` | Added idempotency lock + telemetry `do_action` |
| `khm-plugin.php` | Added `khm_quoteclub_invite_accepted` log listener |
| `tests/bootstrap.php` | `do_action()` stub now records fired actions |
| `tests/Rest/QuoteClubControllerTest.php` | Two new tests: telemetry + 409 lock |
| `tests/UI/quoteclub_invite_flow.spec.js` | Rewritten as deterministic harness-based tests |
| `tests/UI/quoteclub_harness.html` | **New:** standalone invite flow test harness |
| `tests/UI/playwright.quoteclub.config.js` | **New:** dedicated Playwright config with Python webServer |
| `tests/UI/package.json` | Added `test:quoteclub` script and `jquery` devDep |
| `.github/workflows/mem-06-qa.yml` | Added `quoteclub-invite-ui` CI job |
| `docs/membership/mem06_testing_qa_runbook.md` | Added Quote Club browser gate section |
| `docs/RELEASE_RUNBOOK.md` | Added `quoteclub-invite-ui` to Preflight checklist |

---

## Test Coverage Summary

| Layer | Result |
|---|---|
| PHPUnit (full suite) | 154 tests, 628 assertions, 0 failures |
| PHPUnit — idempotency 409 | ✅ passes |
| PHPUnit — telemetry fires once | ✅ passes |
| Playwright — happy path accept | ✅ passes |
| Playwright — retry on transient error | ✅ passes |

---

## How to Enforce in GitHub Branch Protection

Go to **Repository → Settings → Branches → Branch protection rules → `main`** and add `quoteclub-invite-ui` to the **Required status checks** list. This ensures no PR can be merged while the invite browser gate is red.
