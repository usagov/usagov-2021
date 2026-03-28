# Autologout Cross-Tab Testing Guide

Tests for the bug described in [autologout-drupal10.6-crosstab-bug.md](autologout-drupal10.6-crosstab-bug.md).

Two approaches are provided: a **quick manual test** (~5 min, one browser session) and an **automated Nightwatch test** (one command after setup).

---

## What the Tests Prove

The cross-tab bug has a specific, testable signature:

- **Bug (10.6 + old patch):** Tab A logs out even when Tab B keeps the server session alive but the client-side `localStorage` activity flag has expired.
- **Fix (patched server or 2.0.2):** Tab A stays logged in as long as the server session is still valid, regardless of the client-side flag.

Both tests isolate this by having Tab B refresh the **server** session (so remaining time > 0) while deliberately keeping the `localStorage` activity flag **clear**. In the broken state, the inactive tab always logs out. In the fixed state, it stays in.

---

## Quick Setup (both approaches)

Shorten the timeout so you don't wait 30 minutes:

```bash
drush config:set autologout.settings timeout 30 -y
drush config:set autologout.settings padding 5 -y
drush cr
```

Restore after testing:

```bash
drush config:set autologout.settings timeout 1800 -y
drush config:set autologout.settings padding 20 -y
drush cr
```

---

## Approach 1: Quick Manual Test (~5 min)

### Steps

1. Shorten the timeout (above).
2. Log in to the CMS in a browser.
3. Open **two tabs** to any admin page (e.g., `/admin/content`).
4. In **Tab B** (the "active" tab), open the browser console and paste the **Tab B script** below.
5. Leave **Tab A** alone — do not interact with it.
6. Wait **~40 seconds** and observe Tab A.

### Tab B Console Script

This keeps the server session alive while deliberately NOT setting the `localStorage` activity flag. This is the exact condition that exposes the bug.

```javascript
(function keepServerSessionAlive() {
  // Clear the cross-tab activity flag so Tab A sees userIsActive() === false.
  localStorage.removeItem('autologout_page_activity');

  // Ping the server to keep autologout_last current (onRequest updates it).
  fetch(location.href, { credentials: 'same-origin', cache: 'no-store' })
    .then(() => console.log('[autologout test] server session refreshed, flag cleared:', new Date().toLocaleTimeString()));

  setTimeout(keepServerSessionAlive, 8000);
})();

console.log('[autologout test] Tab B running. Watch Tab A for logout.');
```

### What to Observe in Tab A

**Bug present (Drupal 10.6 + old patch):**
- At ~30 seconds: the autologout dialog appears in Tab A **OR** Tab A is silently redirected to `/user/login`.
- The server session was valid (Tab B kept refreshing it) but Tab A logged out anyway.

**Bug fixed (patched controller or autologout 2.0.2):**
- At ~30 seconds: Tab A's timer fires, the server checks actual remaining time, finds it > 0 (Tab B kept it alive), and resets Tab A's timer.
- Tab A stays on the admin page. No dialog, no logout.

### Optional: Tab A Console Monitor

Paste in Tab A to log what happens without watching constantly:

```javascript
let checks = 0;
const monitor = setInterval(() => {
  const flag = localStorage.getItem('autologout_page_activity');
  const dialog = document.getElementById('autologout-confirm');
  const redirected = location.href.includes('/user/login') || location.href.includes('autologout_timeout');

  console.log(
    `[Tab A check ${++checks}]`,
    `flag: ${flag ?? 'null'}`,
    `| dialog: ${dialog ? 'OPEN' : 'none'}`,
    `| redirected: ${redirected}`,
    `| url: ${location.pathname}`,
    new Date().toLocaleTimeString()
  );

  if (redirected) {
    clearInterval(monitor);
    console.error('[Tab A] LOGGED OUT — bug confirmed.');
  }
}, 3000);

console.log('[autologout test] Tab A monitoring started.');
```

---

## Approach 2: Automated Nightwatch Test

A single-command test that opens both tabs, runs the scenario, and asserts the outcome.

### Prerequisites

```bash
# Set short timeout (must be done before running — Nightwatch can't reload config at runtime).
drush config:set autologout.settings timeout 30 -y
drush config:set autologout.settings padding 5 -y
drush cr

# Required environment variables:
export DRUPAL_TEST_BASE_URL="http://localhost"        # Your local site URL
export AUTOLOGOUT_TEST_USER="content_editor_user"    # A user subject to autologout
export AUTOLOGOUT_TEST_PASS="their_password"
```

### Running the Test

```bash
cd web/core
yarn run nightwatch --tag autologout-cross-tab
```

The test file lives at:
`web/modules/contrib/autologout/tests/src/Nightwatch/Tests/autologoutCrossTabTest.js`

### Interpreting Results

| Result | Meaning |
|--------|---------|
| `PASS: Tab A remained logged in` | Cross-tab protection works — fix is effective |
| `FAIL: Tab A was redirected to login` | Bug is present — inactive tab logged out despite valid server session |

### Cleanup

```bash
drush config:set autologout.settings timeout 1800 -y
drush config:set autologout.settings padding 20 -y
drush cr
```

---

## Reading the Autologout Debug Output

When using the Tab A console monitor or reading the Nightwatch output, key signals:

| Signal | Interpretation |
|--------|---------------|
| `localStorage flag: null` | `userIsActive()` returns `false` — server will receive `uactive=false` |
| Dialog appears at ~30s | Timer fired, server returned `time: 0` (bug) |
| No dialog after 40s | Server returned actual remaining time, timer reset (fix) |
| Redirect to `/user/login?autologout_timeout=1` | Full logout completed |
| Network request to `autologout_ajax_get_time_left` with `uactive=false` | Inspect the **response**: if `time: 0` and session is valid → bug confirmed |

To inspect the network request directly: in the Tab A console, open DevTools → Network tab, filter by `autologout_ajax_get_time_left`, and examine the POST request body and JSON response after the 30-second mark.
