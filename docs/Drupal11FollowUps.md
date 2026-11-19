# Drupal 11 upgrade: follow-up tracker

Items that the D11 upgrade (USAGOV-2511, core bump USAGOV-2961) deferred, worked around
or uncovered. Background and verification details are in
[Drupal11UpgradePlan.md](Drupal11UpgradePlan.md).

**How to use:** when an item is picked up, add its ticket/PR next to the title; when it is
resolved, check the box and note how. Keep items that turn out to need no action, with the
reason, rather than deleting them.

Each item lists **what** it is, **why** it was left this way, the **risk** if ignored,
the **action** (or trigger to revisit), and **refs**.

## Before or at deploy

- [x] **1. `symfony/cache` is held at 7.4 only by the lock file.** Resolved in
  USAGOV-2961: `"symfony/cache": "^7.4"` added to `require`. Remove it once the runtime is
  PHP 8.4+.
  - What: `drush` 13.8's new dependency `consolidation/self-update` accepts
    `symfony/cache` `^8`, and 8.x requires PHP ≥ 8.4. The bump pinned it to 7.4.23 with a
    one-off `composer update symfony/cache --with symfony/cache:^7.4`; nothing in
    `composer.json` keeps it there.
  - Risk: the next broad `composer update` pulls 8.x again (see item 2), and Drush's
    self-update code fatals on the PHP 8.3 runtime.
  - Action: add `"symfony/cache": "^7.4"` to `require` until the runtime is PHP 8.4+, then
    remove it.
  - Refs: `composer.json`, `composer.lock`; USAGOV-2961.

- [ ] **2. `bin/composer` ignores all platform requirements.**
  - What: `bin/composer` runs `composer … --ignore-platform-reqs` in the composer container
    (PHP 8.5, no `ext-gd`), while the app runs PHP 8.3.36. Composer therefore never checks
    package PHP requirements against our real runtime. This is how item 1 happened.
  - Risk: any update can silently select packages that need a newer PHP.
  - Action: set `config.platform.php` to the runtime version and replace the blanket flag
    with `--ignore-platform-req=ext-gd` (or add `ext-gd` to the composer container). Until
    then, after every update check each locked package's `php` requirement against the
    runtime (see `.claude/d11-verify/` notes in the plan doc).
  - Refs: `bin/composer`, `docker-compose.yml` (composer service).

- [ ] **3. Verify on cloud.gov (test, then DR) before production.**
  - What: local verification can't exercise these:
    - Redis over **TLS** (production sets `scheme: tls`) with patched redis 1.9.0;
    - s3fs file reads and uploads;
    - SAML login;
    - content_lock and autologout;
    - a Tome static build and `tome-sync`;
    - cron;
    - the browser console under jQuery 4.0 (custom JS was scanned; contrib JS was not).
  - Risk: a production-only failure in caching, files, login, or the static site.
  - Action: test checklist on the test environment, then DR.
  - Refs: `web/sites/default/settings.php` (redis block), `scripts/tome-static.sh`,
    `scripts/tome-sync.sh`.

- [x] **4. Local Tome static build diff.** Done 2026-11-17. A full `drush tome:static`
  build ran on D10 and on D11, from the same database, with no errors: 2,617 files on D10,
  2,602 on D11.
  - File-set differences are core assets only: 15 `system` component CSS files and icons
    are gone in D11, and `item-list.module.css` moved to `core/misc/components/`.
  - After normalizing asset versions and cache-busters, the content differences are:
    - the known D11 core markup changes;
    - `core/misc/drupal.js` and `views.module.css` (core assets);
    - item 21 (the federal directory CSV);
    - 22 redirect-stub pages, all explained by item 22.
  - Tome must be run as production does (`cd web && drush tome:static …`). With
    `drush --root=…`, Tome builds a broken worker command and every worker fails (item 24).

- [ ] **21. Federal directory CSV export formatting changed on D11.**
  - What: in `/admin/directory_report_federal.csv` (EN and ES, published on the static
    site), the multi-value "Contact" link column changes from `  URL, Title` on one
    indented line (D10) to `URL`, a line break, then `, Title` (D11). Same data, still valid
    CSV (the values are quoted); 2,637 vs 4,056 physical lines.
  - Risk: anything parsing that column's text (downstream consumers of the export) sees
    different line breaks.
  - Action: confirm who consumes the export. If the old format matters, adjust the
    `views.view.federal_directory_export` field rewrite or template to emit the D10 format,
    and verify against the D10 file.
  - 2026-11-17:
    - Parsed as CSV, both files have 608 rows, 25 columns and the same header. Every cell is
      identical once whitespace is ignored; only line breaks differ, in Website (607
      cells), Contact (507) and Find an Office Near You (114). Reviewed in Google Sheets
      before the PR.
    - The same view's JSON displays power the agency-index search-as-you-type
      (`searchbar.js`). They are **byte-identical** on D10 and D11
      (`/sites/default/files/directory_report_federal`, `_es`), and a browser test of
      `/agency-index` ("commerce", "homeland security") passes on D11.

- [ ] **22. 41 paths are both a redirect source and an active alias, so static output is
  order-dependent.**
  - What: for example, node 946 has two active English aliases. The old one,
    `/agencies/public-diplomacy-and-public-affairs`, is also the source of a redirect to the
    node. Tome writes that path both as a page and as a redirect stub, and the last writer
    wins, so a page, a redirect, or a stub pointing at itself can appear in different builds.
    All 22 unexplained page differences between the D10 and D11 builds are in this set.
    This is not a D11 regression: outside Tome, both versions resolve alias and redirect
    identically.
  - Risk: a redirect loop or stale duplicate page on the static site, varying per build.
    The Spanish paths are lower impact, since `/es` is redirected to English (USAGOV-2946).
  - Action: clean up the data. For each conflict, delete the old alias (keeping the
    redirect) or delete the redirect. Query: redirect sources joined to active `path_alias`
    rows with the same alias and langcode.

- [ ] **23. An in-place D11 → D10 downgrade needs `symfony/runtime` in `allow-plugins`.**
  - What: with D11's vendor installed, `composer install` on `dev` refuses to run because
    the installed `symfony/runtime` plugin isn't allowed in `dev`'s `composer.json`.
  - Risk: a rollback that reuses an existing `vendor/` fails. Fresh image builds are fine.
  - Action: if a rollback re-uses vendor, temporarily set
    `allow-plugins.symfony/runtime: false` (or delete `vendor/`) first. Note it in the
    production rollback plan.

- [x] **25. Core 11.4.13 is available.** Resolved 2026-11-19: the bump now targets
  11.4.13.
  - What: 11.4.13 was released on 2026-11-18 with 10.6.22 as its D10 counterpart (same
    security release). 10.6.22 reached prod in the USAGOV-2967 omnibus hotpatch, and reaches
    `dev` through the prod back-merge (#2969). Staying on 11.4.12 would have taken production
    backwards on that security release.
  - Verified starting from a 10.6.22 database (`updb` 10.6.22 → 11.4.12 → 11.4.13). 11.4.13
    has no update hooks and no config changes. The only lock change is `drupal/core*`;
    `-W` also offered `guzzlehttp/psr7` 2.13.5, which was held at 2.13.4 to match `dev`.
    Pages (475 anonymous plus 6 logged-in) are byte-identical apart from the `?v=` core
    version strings, and status codes are unchanged. `usapubcsv` is identical (1,628 lines).
    Cypress regression: 62 pass, with the 2 known local asset 404s.

- [ ] **26. `dev` is missing the paragraphs_entity_embed 4.0.1 config export.**
  - What: USAGOV-2954 (#2954) updated paragraphs_entity_embed to 4.0.1, whose update hooks
    add `disable_paragraph_search_filter: false` and `negate: false` to
    `embed.button.paragraphs` and `embed.button.paragraph_components`. The exported config
    wasn't committed, so on `dev` `cim` strips the keys again after `updb`. Both default to
    `false`, so behaviour is unchanged.
  - Action: the USAGOV-2961 branch carries the export. If the bump is delayed, export it on
    `dev` separately.

- [ ] **24. Tome breaks when drush is called with `--root`.**
  - What: `drush --root=/var/www/web tome:static` makes Tome launch its workers with
    `'drush.php --root=…'` as a single quoted command, which fails ("not found").
    Production's `tome-static.sh` calls plain `drush` from `web/` and works.
  - Action: none for production; documented so nobody "fixes" the scripts to use `--root`.

## Planned follow-ups

- [ ] **5. redis is patched instead of upgraded.**
  - What: redis 1.9.0 can't bootstrap on 11.2+ (it calls `\Drupal::database()` while the
    container is being compiled). We added `patches/drupal/redis-container-guard-d11.patch`,
    the same one-line `\Drupal::hasContainer()` guard redis 1.11 ships.
  - Why: upgrading to 1.11 or 2.x needs our TLS-for-Predis patch rebased (it isn't upstream).
    The CONFIG-command patch is only partly upstream in 1.11: `ReportController.php:270`
    still reads `maxmemory_policy` unguarded.
  - Action: upgrade redis, rebase the TLS patch, re-check the CONFIG patch, and **drop the
    guard patch**. Test TLS on cloud.gov.
  - Refs: `composer.json` → `extra.patches.drupal/redis`.

- [ ] **6. `node_menus` is pinned to a dev commit.**
  - What: only `dev-3.x` supports D11; pinned to
    `dev-3.x#1a05015a264176644124a8a1a270837bdc76effc`.
  - Action: watch for a tagged 3.x release with `^11`; move to it (or a newer pinned
    commit) deliberately, with the menu/breadcrumb verification from the plan doc.

- [x] **7. `views_menu_children_filter` 4.0.2 was folded into the bump.**
  - What: 3.0.0-rc4 is fatal on 11.4 (`create()` signature); 4.0.2 also supports `^10.5`,
    so strictly it was D10 prep work. Changes are minimal and `correctMenuChildren_d10.patch`
    applies unchanged. No action; recorded for completeness.

- [ ] **8. uswds_base 2.15 markup is pinned in `usagov`.**
  - What: so the uswds_base 3.12.1 upgrade changed no output, these keep the 2.15 markup:
    - `web/themes/custom/usagov/templates/uswds-base-2.15-pins/`: `field`,
      `block--page-title-block` and `region--sidebar_first`;
    - `web/themes/custom/usagov/preprocess/form_element_label.preprocess.inc`.
  - Action: to adopt the 3.x markup, delete a pin and do visual QA. Each file says what
    3.x would change.
  - Refs: USAGOV-2953 (#2952).

- [ ] **9. content_lock preview-button patch may be unnecessary.**
  - What: `patches/drupal/content_lock-preview-button-lock-release.patch` (USAGOV-2626) was
    ported to 3.0. Its original bug didn't reproduce, even on 2.4 without the patch; its
    "Preview releases the lock" half still has an effect on 3.0.
  - Action: get the original repro from Jacob Yeager; drop the patch if 3.0 no longer needs
    it.
  - Refs: USAGOV-2950 (#2950).

- [ ] **10. Maintenance notes for `usagov_menus`.**
  - `UsagovMenuBasedBreadcrumbBuilder::applies()` is a copy of menu_breadcrumb 2.0.1's;
    re-diff it whenever menu_breadcrumb is updated.
  - `src/ProxyClass/Menu/UsagovMenuActiveTrail.php` is generated; regenerate it
    (`core/scripts/generate-proxy-class.php`) after core updates or when
    `UsagovMenuActiveTrail`'s public methods change. It was regenerated for 11.4.
  - `UsagovMenuActiveTrail` intentionally differs from core: when no link is enabled, the
    route's first link by mlid still wins (error and thank-you pages need their trail).
  - Refs: USAGOV-2941 (#2933).

## Existing bugs and clean-up found during the upgrade

- [ ] **11. Header logo `src` has the wrong filename case.** `top_nav.html.twig:6` uses
  `USAGov_logo_51px.png`; the file is `USAGov_Logo_51px.png` (the `srcset` on line 8 is
  correct). The fallback `src` 404s on case-sensitive Linux, including cloud.gov, and
  Cypress `homepage.cy.js` BTE 2 fails on it.
- [ ] **12. Orphaned field definition.** `block_content.field_place_above_header` was
  removed from config in USAGOV-1685, but its installed definition remains, so the status
  report shows "Mismatched entity and/or field definitions". Uninstall the stored definition.
- [ ] **13. Duplicate array keys** in
  `usagov_ssg_postprocessing/src/Drush/Commands/StaticImageSyncCommands.php:438,440`.
- [ ] **14. Redundant `setPublished(FALSE)`** in
  `usagov_unpub_es/src/Drush/Commands/UnpubCommand.php:87`. Harmless (it is followed by
  `setUnpublished()`); remove for clarity.
- [ ] **15. Config schema warnings.** D11's local lenient schema checker
  (`web/sites/development.services.yml`) reports:
  - `system.performance:stale_file_threshold` (missing schema);
  - `views.view.samlauth_map`, `views.view.moderated_content`,
    `views.view.user_admin_people` (stale or mistyped view options).
- [ ] **16. phpstan: 28 findings.** These are existing code-quality findings, plus a
  docblock-precision note in `usagov_wizard/src/MenuChecker.php:152` from
  phpstan-drupal 2.2. Fix them or add a phpstan baseline so new findings stand out.
- [ ] **17. `update.settings` exists only partially.** The site never had this config; a D11
  post-update created it with just `notification.emails: {}`. Decide whether update-status
  notifications should be configured.

## Housekeeping and process

- [ ] **18. Lock file churn from the composer container.** Its newer Composer adds
  `published-time` entries and `plugin-api-version` 2.11 to `composer.lock`; these have been
  stripped by hand on every D11 PR. Align the Composer version used by the team.
- [ ] **19. Branches and PRs.**
  - After the bump merges: close #2876 (superseded; the plan doc now travels with the
    bump), and delete `USAGOV-2511-upgrade-prep`.
  - Decide what to do with #2906 (the change-set overview).
  - Delete the local D10 snapshot `d10-pre-bump.sql` once production is stable.
- [ ] **20. Drupal 12 readiness (notes, no action yet).**
  - `core_version_requirement` will need `^12`.
  - Contrib deprecations removed in D12: feeds `FileSystemInterface::EXISTS_*`,
    field_group `RenderElement`, faqfield's Twig `spaceless`.
  - The uswds_base pins (item 8), and Twig 4.
