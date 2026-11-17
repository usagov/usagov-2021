# Drupal 10.6.20 → 11.4.12 Upgrade Plan

Ticket: USAGOV-2511

Assessment performed against `dev` @ `e683781f8` (core 10.6.17), using
`drupal/upgrade_status` 4.3.10 and composer dependency resolution. All version and
constraint claims below were verified against the package metadata, not assumed.
Core has since moved to 10.6.20 (USAGOV-2930).

## Target version

Core 11.4.12, the latest 11.4 patch release (retargeted from 11.4.11 on 2026-11-17).
It satisfies `s3fs`'s banded core constraint (`>=11.4.3 <11.5`), the 11.2
hook-ordering floor `node_menus` 3.x-dev needs, and includes the SA-CORE-2026-013 fix
(11.4.7+). 11.5 is excluded by `s3fs`.

## Scope of this document

This records what the upgrade requires, what was found to break, and the order of
operations. **All D10-compatible preparation is complete and merged to `dev`** (see
"Final D10 verification" below); the remaining work is the core bump itself
(USAGOV-2961), which carries this document.

## Preparation already committed

`drupal/upgrade_status` is added to `require-dev`, pinned to `5.0.0-alpha3`, and
enabled only through `local_split` (USAGOV-2905, #2902). Version 4.3.10 calls
`DeprecationMessage::setFile()`, which does not exist on the class, so
`drush upgrade_status:analyze` fatals on any extension whose Twig templates attach
a deprecated library ([#3607698](https://www.drupal.org/i/3607698)). That was fixed
on 5.x only, so 5.0.0-alpha3 replaces the original 4.3.10 + local patch. Remove the
module once the upgrade is complete.

## Compatibility verdict

| Requirement | Needed for 11.4.12 | Current | Status |
| --- | --- | --- | --- |
| PHP | `>= 8.3.0` | 8.3.36 | OK |
| PHP extensions | 14 required | all present | OK |
| MariaDB | `>= 10.6` | 11.4 | OK |
| Drush | 13 supports D11 | 13.7.7 | OK |

No platform or container work is required. This is purely a dependency and code
upgrade.

## Custom code status

`upgrade_status` scanned all 22 custom modules and the `usagov` theme and reported
**zero "Fix now" findings**. The full site scan (87 extensions) produced only two
"Fix now" items, both in contrib, and both resolved by the version bumps below.

Remaining custom-code items, none of which block the upgrade now. The one blocking
item, the `ConfigFormBase` constructor (found after the scan, because `upgrade_status`
does not report it), is fixed in USAGOV-2906:

| Item | Count | Notes |
| --- | --- | --- |
| ~~`core_version_requirement` capped at `^10`~~ | 22 `.info.yml` files | **Done** — all set to `^10 \|\| ^11` in USAGOV-2903 (#2901). Originally 26; 4 were removed with Benefit Finder (USAGOV-2833). |
| ~~`DependencySerializationTrait` with `private` properties~~ | 8 files | **Done** in USAGOV-2906 (#2904; file list there). 8 properties changed to `protected`. `BenefitCategorySearchForm` and `ToggleStaticSiteGenerationForm` had a `$log_channel` from `logger.factory->get()` that could not be serialized; they now log through `FormBase`'s `getLogger()` instead. All 8 classes pass a serialize round-trip. |
| ~~`ConfigFormBase` subclasses call `parent::__construct($config_factory)` without `$typedConfigManager`~~ | 2 — `LoginSettingsForm`, `OrphanedEntitiesSettings` | **Done** in USAGOV-2906 (#2904). Both now inject `config.typed` and pass it to the parent constructor. `OrphanedEntitiesSettings` no longer redeclares the untyped `$typedConfigManager`. The 10.2 deprecation is gone, and both forms save without errors. |
| ~~Twig `spaceless` filter~~ | 1 — `node--state-directory-record--full.html.twig:48` | Deprecated in Twig 3.12, removed in Twig 4. **Done** (USAGOV-2949, #2949): replaced with `\|render\|trim\|raw` on the city field; output byte-identical. |
| ~~`wizardstep` library missing extension name~~ | 1 template | **Done** (USAGOV-2949, #2949): now `usagov/wizardstep`. |
| phpstan findings in `usagov_directories/utility/*.php` | ~12 | Standalone CLI scripts, not Drupal runtime. Pre-existing, unrelated to D11. |
| Duplicate array keys — `StaticImageSyncCommands.php:438,440` | 2 | Real bug, pre-existing, unrelated to D11. |

## Contrib upgrades required

Twelve modules blocked `drupal/core` 11.4.7 at assessment time. All have D11-compatible
releases available. The four low-risk ones are done (USAGOV-2900, #2896), and `ctools`, `faqfield`,
`paragraphs_entity_embed` and `address` are done (USAGOV-2907, #2905), and `log_stdout` is done (USAGOV-2942, #2934). `content_lock` (USAGOV-2950, #2950) and `uswds_base` (USAGOV-2953, #2952) are done; only `node_menus` remains, and it ships with the core bump.

`content_lock`, `uswds_base` and `log_stdout` all allow `^10` at their targets, so
each is upgraded on D10 as its own ticket, ahead of the core bump. `node_menus`
`3.x-dev` requires `^11.2`, so it ships with the core bump.

| Module | Current | Target | Core constraint at target | Risk |
| --- | --- | --- | --- | --- |
| ~~`address`~~ | ~~1.12.0~~ removed | ~~2.0.4~~ | — | major — **Removed** (unused; USAGOV-2907, #2905) |
| ~~`content_lock`~~ | ~~2.4.0~~ 3.0.0 | 3.0.0 | `^10.2 \|\| ^11` | major, plus patch rewrite — **Done** (USAGOV-2950, #2950) |
| ~~`ctools`~~ | ~~3.15.0~~ 4.1.1 | 4.1.1 | `^9.5 \|\| ^10 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| ~~`faqfield`~~ | ~~7.1.0~~ 8.0.1 | 8.0.1 | `^8 \|\| ^9 \|\| ^10 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| ~~`paragraphs_entity_embed`~~ | ~~3.0.1~~ 4.0.0 | 4.0.0 | `^10.3 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| ~~`uswds_base`~~ | ~~2.15.0~~ 3.12.1 | 3.12.1 | `^10 \|\| ^11` | major, theme — **Done** (USAGOV-2953, #2952); 2.15 markup pinned in `usagov` |
| ~~`log_stdout`~~ | ~~1.5.0~~ 3.0.0 | 3.0.0 | `^8.8 \|\| ^9 \|\| ^10 \|\| ^11` | major ×2 — **Done** (USAGOV-2942, #2934); config needs `severity_level: 7` |
| `node_menus` | 3.0.0 | `dev-3.x#1a05015a` | `^11.2 \|\| ^12` | no stable D11 release; ships with the core bump (USAGOV-2961), pinned to a commit |
| ~~`field_defaults`~~ | ~~2.0.0~~ 2.1.1 | 2.1.1 | `^10.0 \|\| ^11.0` | low — **Done** (USAGOV-2900, #2896) |
| ~~`image_style_warmer`~~ | ~~1.2.0~~ 1.3.0 | 1.3.0 | `^9 \|\| ^10 \|\| ^11` | low — **Done** (USAGOV-2900, #2896) |
| ~~`new_relic_rpm`~~ | ~~2.1.1~~ 2.3.0 | 2.3.0 | `^10.1 \|\| ^11` | low — **Done** (USAGOV-2900, #2896) |
| ~~`s3fs`~~ | ~~3.10.0~~ 3.11.0 | 3.11.0 | `>=11.4.3 < 11.5` | low — **Done** (USAGOV-2900, #2896) |

### `node_menus` has no stable D11 release

Only `3.x-dev` declares `^11`. The project already sets `minimum-stability: dev`,
so this resolves, but it means shipping a dev branch of a module that is
load-bearing for menu behaviour here — it is tied to the `usagov_menus` custom
module, including the mlid-ordered active trail that replaces the `correctActiveTrail`
core patch. **Decided (2026-11-17):** pin to the current `dev-3.x` head,
`dev-3.x#1a05015a264176644124a8a1a270837bdc76effc`, for reproducible builds; updating
it later is a deliberate change.

### `s3fs` has a narrow, gapped constraint

`>=8.8 <10.7 || >=11.0 <11.2 || >=11.2.3 <11.4.0 || >=11.4.3 <11.5`. Core 11.4.12
falls in the final window, so it resolves. 3.12.0 carries the same constraint. This module has historically lagged core
point releases, and it will constrain which core patch versions can be taken.
Already at 3.11.0 (USAGOV-2900); the constraint still applies when choosing the core
version.

## Patch status

Three of nine referenced patches need work.

| Patch | Package | Status |
| --- | --- | --- |
| `correctActiveTrail_d10.patch` | core | **Fails** — 2 of 5 hunks. Replaced by `usagov_menus` code on D10 (USAGOV-2941, #2933, **done**); no rebase. |
| `viewsSuggestions-10_3.patch` | core | **Fails** — 2 hunks. **Dropped** instead of rebased (USAGOV-2951, #2951, **done**). |
| `content_lock-preview-button-lock-release.patch` | content_lock | **Fails** — both hunks. Ported to 3.0's `src/Hook/FormAlter.php` (USAGOV-2950, #2950, **done**). |
| `correctMenuChildren_d10.patch` | views_menu_children_filter | OK — package version unchanged. |
| `autologout-multitab-timeout-fix.patch` | autologout | OK — version unchanged. |
| `tomeAggregationFix.patch` | tome | OK — version unchanged. |
| `deduplicateTomeInvokePaths.patch` | tome | OK — version unchanged. |
| `tomePathCountFixes.patch` | tome | OK — version unchanged. |
| 2 × redis patches (remote URLs) | redis | OK — version unchanged. |

### `correctActiveTrail_d10.patch`

The patch makes the active trail pick the original `node_menus` link (lowest `mlid`)
over "false children", by threading a `$sort_order` argument through
`MenuLinkManager` and `MenuTreeStorage(Interface)`. Against 11.4.x it fails on
context drift: D11 restructured `MenuActiveTrail::getActiveLink()` to add `<front>`
handling via `pathMatcher->isFrontPage()` and to select the first *enabled* link.

Rather than rebase it, USAGOV-2941 replaces it with code in `usagov_menus` that runs
unchanged on 10.6 and 11.4, so it ships and is tested on D10:

- A service provider swaps the classes of `plugin.manager.menu.link` and
  `menu.active_trail`, keeping core's constructor arguments.
- The `MenuLinkManager` subclass accepts an optional 4th `$sort_order` argument and,
  for `['mlid' => ...]`, reorders core's result by `menu_tree.mlid`. The
  `correctMenuChildren_d10.patch` call keeps working unchanged.
- The `MenuActiveTrail` subclass uses D11's logic (route links, then `<front>` links
  on the front page, first enabled link wins), with both lookups sorted by `mlid`.
  Unlike core, if no link is enabled the route's first link by `mlid` still wins, so
  pages kept out of the menu with a disabled link (error and thank-you pages) keep
  their trail; a disabled `<front>` link never wins.
- `menu_breadcrumb`'s builder is swapped for a subclass whose `applies()` (copied from
  menu_breadcrumb 2.0.1) builds its derived trail with the `usagov_menus` class.
  `derived_active_trail` stays `true` because `drush usapubcsv` builds breadcrumbs per
  node. Re-diff it whenever menu_breadcrumb is updated.
- `menu.active_trail` is a lazy service, so its generated proxy class lives in
  `usagov_menus/src/ProxyClass`; regenerate it if the class's public methods change.

The core patch and its `composer.json` entry are removed in the same change
(USAGOV-2941, #2933, **done**). Verified byte-identical on 256 pages and the
1,628-line `usapubcsv` export.

### `viewsSuggestions-10_3.patch`

**Dropped** on D10 in USAGOV-2951 (#2951, **done**) instead of rebased. Its only
effect was listing per-view/per-display template suggestions in Twig debug output
(a developer aid); rendered pages are byte-identical without it, and Views still uses
those templates when they exist. With `correctActiveTrail` also gone (USAGOV-2941),
core carries no custom patches, so the core bump has nothing to rebase.

### `content_lock-preview-button-lock-release.patch`

This is a rewrite, not a rebase. `content_lock.module` went from 491 to 208 lines
in 3.0, and the form-alter logic moved into `src/Hook/FormAlter.php` using D11 OOP
hooks. Both changes map cleanly onto the new location: the `['submit', 'publish']`
loop is at `FormAlter.php:53` and the `unlockButton()` call at `FormAlter.php:68`.
Same logic, new file and class context. Ported in USAGOV-2950 (#2950). The bug it
originally fixed (USAGOV-2626) doesn't reproduce with its documented steps even on
2.4 without the patch, but its Preview-releases-lock change still has an effect on
3.0, so it was kept.

3.0 also folds `content_lock_timeout` into `content_lock`: a post-update moves the
1440-minute timeout to `content_lock.settings:timeout: 86400` and uninstalls the
submodule. Run `drush updb` before `drush cim`.

### Orphaned patch files

`removeDependencies.patch`, `samlauthForceHttps.patch`, and
`taxonomy_revision_log.patch` exist in `patches/drupal/` but are referenced nowhere
in `composer.json`. **Done**: deleted in USAGOV-2949 (#2949).

## The `uswds_base` 2.x → 3.x jump

This is the largest unknown and it is unavoidable: `uswds_base` 2.15.0 declares
`^8 || ^9 || ^10`, and the 2.x branch has no D11 release.

The risk is lower than the major version jump implies. The `usagov` theme:

- overrides 101 templates of its own;
- ships its own compiled `css/styles.css` and its own bundled `scripts/uswds.min.js`
  and `scripts/uswds-init.min.js`, so it does not rely on `uswds_base` for USWDS
  assets;
- defines only 10 theme hook functions.

`uswds_base` is therefore mostly supplying template scaffolding that `usagov`
already shadows. The exposure is the templates `usagov` does *not* override, plus
any library or region naming changes between 2.x and 3.x. Budget real visual QA
time; do not assume this is clean.

**Outcome (USAGOV-2953, #2952, done):** regions and the `framework` library are
unchanged, and `usagov` doesn't use the CDN settings. 3.12.1 changes 14 templates
`usagov` inherits and a handful of preprocess functions, but on a 475-page sample only
four changed output, and each is pinned to its 2.15 form in `usagov`:
`templates/uswds-base-2.15-pins/` (`field`, `block--page-title-block`,
`region--sidebar_first`) and `preprocess/form_element_label.preprocess.inc` (required
label class). Rendered output is byte-identical, so no visual QA was needed. Adopting
the 3.x markup (deleting the pins, then visual QA) is an optional follow-up.

## Execution sequence

1. **Prep.** Confirm branch, take a database snapshot (`bin/db-export`), verify
   `bin/` tooling.
2. ~~**Rebase `viewsSuggestions-10_3.patch`**~~ **Dropped** instead (USAGOV-2951,
   #2951, done). No core patches remain: `correctActiveTrail` is replaced by
   `usagov_menus` code (step 5) and the `content_lock` patch is ported with its 3.0
   upgrade (step 5).
3. ~~**Bump `core_version_requirement`** to include `^11` across all 26 `.info.yml`
   files.~~ **Done** in USAGOV-2903 (#2901): 22 files (4 removed with Benefit Finder).
4. ~~**Change the 8 `private` properties to `protected`** in classes using
   `DependencySerializationTrait`.~~ **Done** in USAGOV-2906 (#2904), together with
   the `ConfigFormBase` constructor fix.
5. ~~**Finish the D10-compatible work**, each as its own ticket on 10.6.20.~~ **Done**, all merged to `dev`:
   - Replace `correctActiveTrail_d10.patch` with `usagov_menus` code (USAGOV-2941, #2933).
   - `content_lock` 2.4.0 → 3.0.0, with the preview-button patch ported (USAGOV-2950, #2950).
   - Cleanup: Twig `spaceless`, `wizardstep` library name, orphaned patches (USAGOV-2949, #2949).
   - `log_stdout` 1.5.0 → 3.0.0 (USAGOV-2942, #2934). 3.0 adds a `severity_level` setting with no update hook; without it every level but EMERGENCY is dropped, so config sets `7`.
   - `uswds_base` 2.15.0 → 3.12.1 (USAGOV-2953, #2952). 2.15 markup pinned in `usagov`, so output is byte-identical.
   - Drop `viewsSuggestions-10_3.patch` (USAGOV-2951, #2951). Core now carries no custom patches.
6. **Bump `composer.json`** (USAGOV-2961): the three core packages to `11.4.12`,
   `node_menus` to `dev-3.x#1a05015a264176644124a8a1a270837bdc76effc`. No core patches
   to swap.
7. **`bin/composer update`.** Expect to iterate on transitive conflicts; Symfony
   6.4 → 7.4 underneath core is a large jump.
8. **`drush updb`**, then **`drush cr`**.
9. **`drush config:export`.** Review the diff rather than committing wholesale.
10. **Verify.** `composer phpstan`, `composer php-lint`, `composer phpcs-errors`,
    then the Cypress e2e suite in `automated_tests/e2e-cypress`, and a Tome static
    build diff against the D10 build.
11. **Visual QA** of the theme, concentrating on anything `usagov` does not
    override.
12. **Re-run `upgrade_status`** against D11 to confirm a clean board, then remove
    `upgrade_status` from `composer.json` and `local_split`.

Steps 2–5 land on `dev` ahead of the core bump. Step 6 onward is the core bump itself: it touches
the lockfile and the database, and the snapshot from step 1 is the undo.

## Core bump outcome (USAGOV-2961, 2026-11-17)

Steps 6–12 are done on `USAGOV-2961-drupal-11-core-bump`. Two contrib modules that
declare `^11` still failed at runtime on 11.4, so neither `composer prohibits` nor
`upgrade_status` could have flagged them:

- **`redis` 1.9.0**: 11.2+ clears cache bins while compiling the container, and
  `CacheBase::deleteMultiple()` calls `\Drupal::database()` before one exists, so
  Drupal can't bootstrap. Fixed with a one-line patch
  (`redis-container-guard-d11.patch`, the same `\Drupal::hasContainer()` guard as
  redis 1.11). Upgrading to 1.11 instead needs our TLS-for-Predis patch rebased
  (production uses `scheme: tls`), so that's a separate follow-up; drop this patch then.
- **`views_menu_children_filter` 3.0.0-rc4**: its join plugin's `create()` signature
  is fatal against 11.4's `PluginBase::create()`. Upgraded to 4.0.2 (also supports
  `^10.5`); `correctMenuChildren_d10.patch` applies unchanged.

Other changes the bump needed:

- **Composer.** `symfony/cache` held at 7.4: drush 13.8's new `consolidation/self-update`
  allows 8.x, which needs PHP 8.4, and `bin/composer` ignores platform requirements, so
  every locked package's `php` requirement was checked against 8.3.36 (all pass).
  `symfony/runtime` (now a core dependency) added to `allow-plugins`, as in Drupal's
  recommended-project. `upgrade_status` removed; `mglaman/phpstan-drupal` now required
  directly because `phpstan.neon` loads it and it had only come in through `upgrade_status`.
- **Code.** Front-page `og:url` got a double slash (`html.html.twig`'s `ends with` no
  longer matches rendered markup on 11); six forms' `create()` now return `static`
  (D11's stricter interface docs); the `menu.active_trail` proxy is regenerated
  (`destruct(): void`).
- **Config.** Exported after `drush updb` (not `cim` first, which would revert the
  update hooks): block `depth: null`, views settings defaults, role dependency
  recalculation plus `rebuild node access permissions` for roles that administer nodes
  (no permission lost), `system.rss` and `field.settings` removed, `update.settings`
  created.

**Verification against the D10 baseline** (same 475-path sample, logged-in pages,
`/user/login`): status codes identical; every HTML difference is a D11 core change —
asset version strings and jQuery 3.7.1 → 4.0.0, removed `system` component CSS, views
blocks moving `views-element-container` onto an inner `<div>`, the admin toolbar
rendering its menu inline, and `aria-required` dropped from `required` inputs. No custom
CSS, JS or template targets `views-element-container`, and no custom JS uses an API
jQuery 4 removed. `usapubcsv` is identical (1,628 lines). Cypress regression: 62 pass;
the 2 `homepage.cy.js` failures are the pre-existing local asset 404s. phpstan: 28
findings (27 on D10, plus one docblock-precision note from the newer
`phpstan-drupal`), no deprecations. `upgrade_status` on D11: 0 "Fix now" in custom
code (remaining items are D12 notes). The pre-existing
`block_content.field_place_above_header` definition mismatch (left over from
USAGOV-1685) still shows in the status report. **Not yet done:** a full Tome static
build diff (the page diff covers the same HTML), and testing on the cloud.gov
test/DR environments.

## Anticipated problems

- **Symfony 6.4 → 7.4** under core will surface conflicts in packages that are not
  on the blocker list but carry loose Symfony constraints — `onelogin/php-saml`,
  `phpoffice/phpspreadsheet`, `predis/predis`, and the Drush command classes in
  `usagov_ssg_postprocessing`.
- **Config schema churn** from `ctools` 3→4, which had config schema changes across
  that major. (`address` was removed rather than upgraded.)
- **`paragraphs_entity_embed` 3→4** interacts with `entity_embed`, `paragraphs`, and
  the CKEditor 5 integration. The existence of the
  `usagov_ckeditor5_source_editing_fixup` custom module suggests this area is
  already delicate.
- **`content_lock` 3.0** converted to OOP hooks. Any custom code calling its
  procedural API will break.

## Final D10 verification (2026-11-17)

Run on a clean checkout of `dev` @ `beb4e9db3` (all prep PRs merged), with the local
site synced (`composer install`, `drush updb`, `drush cim`; config in sync):

- **Blockers.** `composer prohibits drupal/core 11.4.12` lists only `node_menus`
  (ships with the bump). Everything else it lists is core's own Symfony 7.4 and
  `masterminds/html5` requirements, resolved by the bump.
- **Core patches.** None; `web/core` is pristine 10.6.20.
- **`upgrade_status` 5.0.0-alpha3**, all installed projects: **0 "Fix now"**.
  - Custom code: only the pre-existing duplicate array keys in
    `StaticImageSyncCommands.php:438,440` (not a D11 issue).
  - Contrib items marked "removed in 11.0": 13 `loadRevision()`/`deleteRevision()` calls
    in `entity_reference_revisions` and `paragraphs_library`. They are typed against
    `EntityStorageInterface`, but the storages they use implement
    `RevisionableStorageInterface`, which still has both methods in 11.4.12, so they
    work at runtime. Both modules declare `^11`.
  - The rest of the "Check manually" items are D11-only attributes (expected on D10),
    deprecations removed only in D12, advisory messages, and contrib test code
    (PHPUnit is not installed).
- **phpstan** (project config, with deprecation rules): **no deprecation findings** in
  custom code. Its 27 other findings are pre-existing code-quality issues; none affect
  D11.

## Rollback

`git checkout dev` plus restoring the database snapshot reverts everything. No
infrastructure changes are involved, so nothing needs unwinding outside the repo
and the local database.

## Open decisions

1. ~~Is `node_menus` on `3.x-dev` acceptable, or should it be pinned to a commit or
   forked?~~ **Resolved:** pinned to `dev-3.x#1a05015a264176644124a8a1a270837bdc76effc`.
2. ~~Should the `['mlid' => 'ASC']` sort also apply to the new `<front>`
   `loadLinksByRoute()` call?~~ **Resolved:** yes, both lookups are sorted
   (USAGOV-2941).
