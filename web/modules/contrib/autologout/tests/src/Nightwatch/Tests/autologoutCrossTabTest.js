/**
 * @file
 * Nightwatch test for the autologout cross-tab bug (USAGOV-2668 / USAGOV-2687).
 *
 * Verifies that an inactive tab (Tab A) remains logged in when another tab
 * (Tab B) keeps the Drupal server session alive via periodic requests, even
 * though the localStorage activity flag is absent in Tab A.
 *
 * The test isolates the Drupal 10.6 regression: the patched autologout 2.0.1
 * module incorrectly logs out Tab A because `ajax.options.data` is now a
 * direct reference to `ajax.submit` (which contains `uactive: false`), so the
 * server's `uactive === "false"` short-circuit returns `time: 0` without
 * consulting the actual session remaining time.
 *
 * Prerequisites — run before this test:
 *   drush config:set autologout.settings timeout 30 -y
 *   drush config:set autologout.settings padding 5 -y
 *   drush cr
 *
 * Restore after testing:
 *   drush config:set autologout.settings timeout 1800 -y
 *   drush config:set autologout.settings padding 20 -y
 *   drush cr
 *
 * Required environment variables:
 *   AUTOLOGOUT_TEST_USER  — CMS username subject to autologout
 *   AUTOLOGOUT_TEST_PASS  — matching password
 */

module.exports = {
  '@tags': ['autologout-cross-tab'],

  /**
   * Cross-tab autologout regression test.
   *
   * Pass condition (fix is effective):
   *   Tab A stays on /admin/content after 45 s. The server returned actual
   *   remaining time (> 0) and Tab A reset its timer, even though
   *   userIsActive() was false.
   *
   * Fail condition (bug is present):
   *   Tab A is redirected to /user/login within ~35 s. The server short-
   *   circuited on uactive=false and returned time: 0 despite a valid session.
   */
  'Tab A stays logged in when Tab B keeps server session alive': function (browser) {
    const username = process.env.AUTOLOGOUT_TEST_USER;
    const password = process.env.AUTOLOGOUT_TEST_PASS;

    if (!username || !password) {
      browser.assert.fail(
        'AUTOLOGOUT_TEST_USER and AUTOLOGOUT_TEST_PASS environment variables must be ' +
        'set before running this test. See docs/autologout-cross-tab-testing.md.',
      );
      return;
    }

    // ── Step 1: Log in and load an admin page in Tab A ──────────────────────

    browser
      .drupalLogin({ name: username, password: password })
      .drupalRelativeURL('/admin/content')
      .waitForElementVisible('body', 5000);

    // Capture Tab A's window handle before opening a second tab.
    let tabAHandle;
    browser.currentWindowHandle(function (result) {
      tabAHandle = result.value;
    });

    // ── Step 2: Open Tab B and navigate to an admin page ────────────────────
    // openNewWindow() automatically focuses the new tab.

    browser
      .openNewWindow('tab')
      .drupalRelativeURL('/admin/content')
      .waitForElementVisible('body', 5000);

    // ── Step 3: Inject the keepAlive loop into Tab B ─────────────────────────
    //
    // This script deliberately reproduces the exact condition that exposes the
    // cross-tab bug:
    //   - It keeps the SERVER session alive by fetching the current page
    //     every 8 seconds (AutologoutSubscriber::onRequest updates
    //     autologout_last on every request).
    //   - It deliberately does NOT set the localStorage flag, so Tab A's
    //     userIsActive() always returns false.
    //
    // In the buggy state the server ignores the live session and returns
    // time: 0 because uactive=false is sent. In the fixed state the server
    // consults getRemainingTime() and returns the actual positive value.

    browser.execute(function () {
      (function keepServerSessionAlive() {
        // Clear the cross-tab activity flag so Tab A sees userIsActive() === false.
        localStorage.removeItem('autologout_page_activity');

        // Refresh the server session (updates autologout_last via onRequest).
        fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' });

        // Repeat every 8 s (well within the 30 s autologout timeout).
        window._autologoutTabBTimer = setTimeout(keepServerSessionAlive, 8000);
      })();
    }, []);

    // ── Step 4: Switch back to Tab A and leave it completely idle ────────────
    //
    // browser.perform() runs its callback inside the Nightwatch command queue,
    // ensuring tabAHandle is populated before switchWindow() is called.

    browser.perform(function () {
      browser.switchWindow(tabAHandle);
    });

    // ── Step 5: Wait for the full autologout sequence to play out ────────────
    //
    // Timeline (30 s timeout / 5 s padding):
    //   t =  0 s  Tab A idle timer starts (no user interaction in this tab)
    //   t = 30 s  init() fires → getTimeLeft AJAX → uactive=false sent
    //             Bug:  server returns time: 0 → paddingTimer starts
    //             Fix:  server returns remaining time → timer reset
    //   t = 35 s  (bug) confirmLogout() → getTimeLeft again → time: 0
    //             → logout() → redirect to /user/login
    //   t = 45 s  We assert. If still on /admin/content, fix is working.

    browser.pause(45000);

    // ── Step 6: Assert Tab A was not logged out ──────────────────────────────

    browser
      .assert.not.urlContains(
        '/user/login',
        'PASS: Tab A was NOT redirected to the login page — ' +
        'cross-tab protection is working.',
      )
      .assert.not.urlContains(
        'autologout_timeout',
        'PASS: Tab A URL has no autologout_timeout parameter — ' +
        'session was preserved by the server.',
      )
      .assert.urlContains(
        '/admin/content',
        'PASS: Tab A remains on /admin/content — ' +
        'inactive tab was not logged out despite userIsActive() === false.',
      );

    // ── Cleanup: close Tab B ─────────────────────────────────────────────────

    browser.windowHandles(function (result) {
      result.value.forEach(function (handle) {
        if (handle !== tabAHandle) {
          browser.switchWindow(handle);
          // Clear the keepAlive timer before closing to avoid console errors.
          browser.execute(function () {
            clearTimeout(window._autologoutTabBTimer);
          }, []);
          browser.closeWindow();
        }
      });
    });

    browser.perform(function () {
      browser.switchWindow(tabAHandle);
    });
  },
};
