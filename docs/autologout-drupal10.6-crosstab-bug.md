# Autologout Cross-Tab Behavior Broken in Drupal 10.6

**Ticket:** USAGOV-2668 / USAGOV-2687
**Module:** `drupal/autologout` 2.0.1 + patch [#3395581-7](https://www.drupal.org/files/issues/2024-06-03/3395581-7.patch)
**Environment:** Drupal 10.6.x

---

## Summary

The patched autologout module allows two browser tabs to share a single 30-minute idle timeout: activity in one tab resets the timer for both. This behavior works in Drupal 10.5 but breaks in Drupal 10.6. After the upgrade, an inactive tab will log the user out even when the other tab is actively in use.

---

## How the Patch Is Supposed to Work

The patch (drupal.org issue [#3395581](https://www.drupal.org/project/autologout/issues/3395581)) adds two mechanisms:

### Client-side: `localStorage` activity flag

When a user interacts with any tab (mousemove, keyup, touchmove, formUpdated), the active tab sets a shared `localStorage` flag:

```javascript
localStorage.setItem('autologout_page_activity', 'true');
```

A 30-second internal reset timer clears the flag if no further activity occurs. `userIsActive()` reads this flag. Any tab can observe activity across all open tabs via this shared value.

### Server-side: short-circuit in `ajaxGetRemainingTime`

When the inactive tab's 30-minute timer fires it calls the server to ask how much time remains, passing the current activity state as `uactive`:

```javascript
// autologout.js:330-337
submit: {
    uactive: userIsActive(),   // false when user inactive for >30 sec
},
```

The server short-circuits immediately when `uactive === "false"`:

```php
// AutologoutController.php:123-131
if (isset($active) && $active === "false") {
    $response->addCommand(new ReplaceCommand('#timer', 0));
    $response->addCommand(new SettingsCommand([
        'time' => 0,
        'activity' => FALSE,
    ]));
    return $response;  // Returns time: 0 — no session check
}
$time_remaining_ms = $this->autoLogoutManager->getRemainingTime() * 1000;
```

When the callback receives `time: 0`, the inactive tab shows the logout dialog and eventually calls `logout()`.

**The intended flow when Tab B is active:**

1. Tab B's user activity → `localStorage['autologout_page_activity'] = 'true'`
2. Tab A's 30-minute timer fires → `userIsActive()` reads localStorage → `true`
3. Tab A calls `refresh()` (not `getTimeLeft`) → server session updated → Tab A timer reset to 30 more minutes

**The intended flow when no tab is active:**

1. Tab A's timer fires → `userIsActive()` → `false`
2. Tab A queries server with `uactive=false` → server returns `time: 0`
3. Tab A shows dialog → eventually logs out

---

## Root Cause: Drupal 10.6 AJAX Constructor Change

### The breaking change

The patch's `getDrupalAjaxTimeLeftObject()` creates a `Drupal.ajax()` object and then fires it by calling `$.ajax(ajax.options)` **directly**, bypassing Drupal's normal `execute()` pathway:

```javascript
// autologout.js:282-323 (autologoutGetTimeLeft)
try {
    $.ajax(ajax.options);   // Direct call — skips execute() and beforeSerialize()
} catch (e) {
    ajax.ajaxing = false;
}
```

Drupal's normal `execute()` calls `beforeSerialize()` before `$.ajax()`:

```javascript
// ajax.js:704-707
Drupal.Ajax.prototype.execute = function () {
    this.beforeSerialize(this.element, this.options);
    return $.ajax(this.options);
};
```

In **Drupal 10.6**, `ajax.options` is initialized with a direct reference to `ajax.submit` at construction time:

```javascript
// ajax.js:532-534
ajax.options = {
    url: ajax.url,
    data: ajax.submit,   // ← direct reference; includes uactive: false
    ...
};
```

Since `getDrupalAjaxTimeLeftObject()` passes `submit: { uactive: userIsActive() }`, and `userIsActive()` returns `false` when the user has been inactive for more than 30 seconds, `ajax.submit = { uactive: false }`. Because `ajax.options.data` now **directly references** `ajax.submit`, calling `$.ajax(ajax.options)` immediately includes `uactive=false` in the POST body.

The server receives `uactive=false`, short-circuits, returns `time: 0`, and the tab logs out.

### Why it worked in Drupal 10.5

In Drupal 10.5, `ajax.options.data` was initialized independently from `ajax.submit`. Calling `$.ajax(ajax.options)` without first running `beforeSerialize()` meant `ajax.submit`'s values (including `uactive`) were **never included** in the request. The server received no `uactive` parameter:

```php
$active = $req->get('uactive');  // null — not sent
if (isset($active) && $active === "false") {  // false — condition not met
```

The server fell through to `getRemainingTime()`, which returns the actual session remaining time. If Tab B had recently refreshed the session (or if `AutologoutSubscriber::onRequest` had updated `autologout_last` on any recent request), the server returned a positive value and Tab A reset its timer.

---

## Secondary Issue: 30-Second Window Fragility

Even when `uactive` behaves correctly, the design has a structural weakness.

`userIsActive()` returns `true` for only **30 seconds** after the last user activity event. Tab A's 30-minute timer fires unconditionally. If the user pauses in Tab B for 31 or more seconds at the exact moment Tab A's timer fires — even though the session is still far from expiring — `userIsActive()` returns `false`, `uactive=false` is sent, the server ignores the session state, and Tab A logs out.

This window existed in 10.5 but was harmless because `uactive` was not being sent. In 10.6, the window is always exploited.

---

## Additional Issue: `autologoutRefresh` Response Corruption

The `autologoutRefresh` function (used when `userIsActive() === true`) wraps the first AJAX command unconditionally:

```javascript
// autologout.js:370
response[0].data = '<div id="timer" style="display: none;">' + response[0].data + '</div>';
```

In Drupal 10.6, `AjaxResponseSubscriber::processAttachments()` may prepend `add_js` or `add_css` library commands to the response before the `insert` command. When this happens `response[0]` is no longer the timer replacement command — it is a library load command — and wrapping its `data` field corrupts the library payload.

This does not directly cause logout (the timer reset `t = setTimeout(...)` executes before the response is processed), but it may produce JS errors and interfere with library loading.

---

## Recommended Fixes

### Option 1: Update to autologout 2.0.2 (recommended)

Branch `USAGOV-2687-update-autologout-module` already upgrades to autologout 2.0.2, which removes the `js_cookie` dependency and the `uactive`/short-circuit mechanism entirely. Version 2.0.2 relies on the server's own `autologout_last` session value (kept current by `AutologoutSubscriber::onRequest` on every request from any tab) as the authoritative source of remaining time. This is simpler and more reliable.

However, the branch also removed js_cookie. Confirm that 2.0.2's `autologout.js` no longer references `window.Cookies` before merging.

### Option 2: Fix the server-side short-circuit (patch-only fix)

Modify `ajaxGetRemainingTime` so that when `uactive === "false"`, it still consults the actual session remaining time before deciding to return `time: 0`. If the session has time remaining (because Tab B refreshed it), return the actual time and let the client decide:

```php
// AutologoutController.php — ajaxGetRemainingTime()
public function ajaxGetRemainingTime() {
    $req = $this->requestStack->getCurrentRequest();
    $active = $req->get('uactive');
    $response = new AjaxResponse();

    $time_remaining_ms = $this->autoLogoutManager->getRemainingTime() * 1000;

    if ($time_remaining_ms <= 0 || (isset($active) && $active === "false" && $time_remaining_ms <= 0)) {
        $response->addCommand(new ReplaceCommand('#timer', 0));
        $response->addCommand(new SettingsCommand([
            'time' => 0,
            'activity' => FALSE,
        ]));
        return $response;
    }

    // Session still has time — return it regardless of client-side activity flag.
    $markup = $this->autoLogoutManager->createTimer();
    $response->addCommand(new ReplaceCommand('#timer', $markup));
    $response->addCommand(new SettingsCommand([
        'time' => $time_remaining_ms,
        'activity' => TRUE,
    ]));

    return $response;
}
```

This restores the Drupal 10.5 behavior: the server's session state is authoritative, and `uactive` is informational only.

### Option 3: Fix the client-side bypass

Call `beforeSerialize()` before `$.ajax(ajax.options)` in both `autologoutGetTimeLeft` and `autologoutRefresh`, matching what `execute()` does. This ensures the request data is properly built before submission, but it also adds `ajax_page_state` headers to every autologout AJAX call, which may cause unnecessary library diffing on each poll.

```javascript
// Before $.ajax(ajax.options) in autologoutGetTimeLeft and autologoutRefresh:
ajax.beforeSerialize(ajax.element, ajax.options);
$.ajax(ajax.options);
```

---

## Affected Files

| File | Role |
|------|------|
| `web/modules/contrib/autologout/js/autologout.js` | Cross-tab activity logic, direct `$.ajax()` calls |
| `web/modules/contrib/autologout/src/Controller/AutologoutController.php` | `uactive` short-circuit on line 123 |
| `web/modules/contrib/autologout/src/EventSubscriber/AutologoutSubscriber.php` | Updates `autologout_last` on every request (server-side session upkeep) |
| `web/core/misc/ajax.js` | Drupal 10.6 change: `data: ajax.submit` at line 534 |

---

## Decision Summary

| Approach | Effort | Risk | Recommended |
|----------|--------|------|-------------|
| Update to autologout 2.0.2 | Low — branch exists | Low — upstream fix | Yes |
| Patch server short-circuit | Medium — custom patch | Low | If 2.0.2 unavailable |
| Fix client-side bypass | Medium — custom patch | Medium — side effects | Last resort |
