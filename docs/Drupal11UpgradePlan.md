# Drupal 10.6.20 → 11.4.11 Upgrade Plan

Ticket: USAGOV-2511

Assessment performed against `dev` @ `e683781f8` (core 10.6.17), using
`drupal/upgrade_status` 4.3.10 and composer dependency resolution. All version and
constraint claims below were verified against the package metadata, not assumed.
Core has since moved to 10.6.20 (USAGOV-2930).

## Target version

Core 11.4.11, the latest 11.4 patch release. It satisfies `s3fs`'s banded core
constraint (`>=11.4.3 <11.5`), the 11.2 hook-ordering floor `node_menus` 3.x-dev
needs, and includes the SA-CORE-2026-013 fix (11.4.7+). 11.5 is excluded by `s3fs`.

## Scope of this document

This is the preparation and planning phase. It records what the upgrade requires,
what is already known to break, and the order of operations. No core bump, contrib
bump, or database change has been performed.

## Preparation already committed

`drupal/upgrade_status` is added to `require-dev`, pinned to `5.0.0-alpha3`, and
enabled only through `local_split` (USAGOV-2905, #2902). Version 4.3.10 calls
`DeprecationMessage::setFile()`, which does not exist on the class, so
`drush upgrade_status:analyze` fatals on any extension whose Twig templates attach
a deprecated library ([#3607698](https://www.drupal.org/i/3607698)). That was fixed
on 5.x only, so 5.0.0-alpha3 replaces the original 4.3.10 + local patch. Remove the
module once the upgrade is complete.

## Compatibility verdict

| Requirement | Needed for 11.4.7 | Current | Status |
| --- | --- | --- | --- |
| PHP | `>= 8.3.0` | 8.3.29 | OK |
| PHP extensions | 14 required | all present | OK |
| MariaDB | `>= 10.6` | 11.4 | OK |
| Drush | 13 supports D11 | 13.7.6 | OK |

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
| Twig `spaceless` filter | 1 — `node--state-directory-record--full.html.twig:48` | Deprecated in Twig 3.12, removed in Twig 4. |
| `wizardstep` library missing extension name | 1 template | Needs `themename/libraryname` form. |
| phpstan findings in `usagov_directories/utility/*.php` | ~12 | Standalone CLI scripts, not Drupal runtime. Pre-existing, unrelated to D11. |
| Duplicate array keys — `StaticImageSyncCommands.php:438,440` | 2 | Real bug, pre-existing, unrelated to D11. |

## Contrib upgrades required

Twelve modules blocked `drupal/core` 11.4.7 at assessment time. All have D11-compatible
releases available. The four low-risk ones are done (USAGOV-2900, #2896), and `ctools`, `faqfield`,
`paragraphs_entity_embed` and `address` are done (USAGOV-2907, #2905), and `log_stdout` is done (USAGOV-2942, #2934); three remain.

`content_lock`, `uswds_base` and `log_stdout` all allow `^10` at their targets, so
each is upgraded on D10 as its own ticket, ahead of the core bump. `node_menus`
`3.x-dev` requires `^11.2`, so it ships with the core bump.

| Module | Current | Target | Core constraint at target | Risk |
| --- | --- | --- | --- | --- |
| ~~`address`~~ | ~~1.12.0~~ removed | ~~2.0.4~~ | — | major — **Removed** (unused; USAGOV-2907, #2905) |
| `content_lock` | 2.4.0 | 3.0.0 | `^10.2 \|\| ^11` | major, plus patch rewrite |
| ~~`ctools`~~ | ~~3.15.0~~ 4.1.1 | 4.1.1 | `^9.5 \|\| ^10 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| ~~`faqfield`~~ | ~~7.1.0~~ 8.0.1 | 8.0.1 | `^8 \|\| ^9 \|\| ^10 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| ~~`paragraphs_entity_embed`~~ | ~~3.0.1~~ 4.0.0 | 4.0.0 | `^10.3 \|\| ^11` | major — **Done** (USAGOV-2907, #2905) |
| `uswds_base` | 2.15.0 | 3.12.1 | `^10 \|\| ^11` | major, theme |
| ~~`log_stdout`~~ | ~~1.5.0~~ 3.0.0 | 3.0.0 | `^8.8 \|\| ^9 \|\| ^10 \|\| ^11` | major ×2 — **Done** (USAGOV-2942, #2934); config needs `severity_level: 7` |
| `node_menus` | 3.0.0 | `3.x-dev` | `^11.2 \|\| ^12` | no stable D11 release; ships with the core bump |
| ~~`field_defaults`~~ | ~~2.0.0~~ 2.1.1 | 2.1.1 | `^10.0 \|\| ^11.0` | low — **Done** (USAGOV-2900, #2896) |
| ~~`image_style_warmer`~~ | ~~1.2.0~~ 1.3.0 | 1.3.0 | `^9 \|\| ^10 \|\| ^11` | low — **Done** (USAGOV-2900, #2896) |
| ~~`new_relic_rpm`~~ | ~~2.1.1~~ 2.3.0 | 2.3.0 | `^10.1 \|\| ^11` | low — **Done** (USAGOV-2900, #2896) |
| ~~`s3fs`~~ | ~~3.10.0~~ 3.11.0 | 3.11.0 | `>=11.4.3 < 11.5` | low — **Done** (USAGOV-2900, #2896) |

### `node_menus` has no stable D11 release

Only `3.x-dev` declares `^11`. The project already sets `minimum-stability: dev`,
so this resolves, but it means shipping a dev branch of a module that is
load-bearing for menu behaviour here — it is tied to the `usagov_menus` custom
module, including the mlid-ordered active trail that replaces the `correctActiveTrail`
core patch. **Open decision:** accept the dev
branch, pin to a specific commit, or fork.

### `s3fs` has a narrow, gapped constraint

`>=8.8 <10.7 || >=11.0 <11.2 || >=11.2.3 <11.4.0 || >=11.4.3 <11.5`. Core 11.4.11
falls in the final window, so it resolves. 3.12.0 carries the same constraint. This module has historically lagged core
point releases, and it will constrain which core patch versions can be taken.
Already at 3.11.0 (USAGOV-2900); the constraint still applies when choosing the core
version.

## Patch status

Three of nine referenced patches need work.

| Patch | Package | Status |
| --- | --- | --- |
| `correctActiveTrail_d10.patch` | core | **Fails** — 2 of 5 hunks. Replaced by `usagov_menus` code on D10 (USAGOV-2941); no rebase. |
| `viewsSuggestions-10_3.patch` | core | **Fails** — 2 hunks. Rebase required. |
| `content_lock-preview-button-lock-release.patch` | content_lock | **Fails** — both hunks. Rewrite required. |
| `correctMenuChildren_d10.patch` | views_menu_children_filter | OK — package version unchanged. |
| `autologout-multitab-timeout-fix.patch` | autologout | OK — version unchanged. |
| `tomeAggregationFix.patch` | tome | OK — version unchanged. |
| `deduplicateTomeInvokePaths.patch` | tome | OK — version unchanged. |
| `tomePathCountFixes.patch` | tome | OK — version unchanged. |
| 2 × redis patches (remote URLs) | redis | OK — version unchanged. |

### `correctActiveTrail_d10.patch`

The patch makes the active trail pick the original `node_menus` link (lowest `mlid`)
over "false children", by threading a `$sort_order` argument through
`MenuLinkManager` and `MenuTreeStorage(Interface)`. Against 11.4.11 it fails on
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

The core patch and its `composer.json` entry are removed in the same change.

### `viewsSuggestions-10_3.patch`

Agreed approach: rebase the functional parts only — `ThemeManager.php` (5 hunks)
and `twig.engine` (1 hunk) — and drop the roughly 24 core test files and fixtures
the original patch carries. This keeps the developer-facing capability (views
template suggestions in Twig debug output) with far less rebase burden on future
core updates.

One caveat: D11 added a *deprecated hint* feature to the exact block this patch
rewrites — `theme_hook_suggestions__DEPRECATED` appends `(deprecated)` to the
template name in debug output. The rebase must preserve it, or that D11 feature is
silently lost.

### `content_lock-preview-button-lock-release.patch`

This is a rewrite, not a rebase. `content_lock.module` went from 491 to 208 lines
in 3.0, and the form-alter logic moved into `src/Hook/FormAlter.php` using D11 OOP
hooks. Both changes map cleanly onto the new location: the `['submit', 'publish']`
loop is at `FormAlter.php:53` and the `unlockButton()` call at `FormAlter.php:68`.
Same logic, new file and class context.

### Orphaned patch files

`removeDependencies.patch`, `samlauthForceHttps.patch`, and
`taxonomy_revision_log.patch` exist in `patches/drupal/` but are referenced nowhere
in `composer.json`. They should be deleted as cleanup.

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

## Execution sequence

1. **Prep.** Confirm branch, take a database snapshot (`bin/db-export`), verify
   `bin/` tooling.
2. **Rebase `viewsSuggestions-10_3.patch`** against 11.4.11 (ticket TBD) *before*
   changing any `composer.json` constraints. Verify it with `patch --dry-run` against
   fetched core sources. A failing patch aborts the entire composer run, so this must
   be clean first. `correctActiveTrail` needs no rebase (step 5), and the
   `content_lock` patch is rewritten with its 3.0 upgrade (step 5).
3. ~~**Bump `core_version_requirement`** to include `^11` across all 26 `.info.yml`
   files.~~ **Done** in USAGOV-2903 (#2901): 22 files (4 removed with Benefit Finder).
4. ~~**Change the 8 `private` properties to `protected`** in classes using
   `DependencySerializationTrait`.~~ **Done** in USAGOV-2906 (#2904), together with
   the `ConfigFormBase` constructor fix.
5. **Finish the D10-compatible work**, each as its own ticket on 10.6.20:
   - Replace `correctActiveTrail_d10.patch` with `usagov_menus` code (USAGOV-2941).
   - `content_lock` 2.4.0 → 3.0.0, with the preview-button patch rewritten.
   - ~~`log_stdout` 1.5.0 → 3.0.0.~~ **Done** (USAGOV-2942, #2934). 3.0 adds a `severity_level` setting with no update hook; without it every level but EMERGENCY is dropped, so config sets `7`.
   - `uswds_base` 2.15.0 → 3.12.1, with visual QA.
6. **Bump `composer.json`**: the three core packages to `11.4.11`, `node_menus` to
   `3.x-dev`, and swap in the rebased `viewsSuggestions` patch.
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

## Rollback

`git checkout dev` plus restoring the database snapshot reverts everything. No
infrastructure changes are involved, so nothing needs unwinding outside the repo
and the local database.

## Open decisions

1. Is `node_menus` on `3.x-dev` acceptable, or should it be pinned to a commit or
   forked?
2. ~~Should the `['mlid' => 'ASC']` sort also apply to the new `<front>`
   `loadLinksByRoute()` call?~~ **Resolved:** yes, both lookups are sorted
   (USAGOV-2941).
