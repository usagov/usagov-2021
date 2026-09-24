# Drupal 10.6.17 → 11.4.7 Upgrade Plan

Ticket: USAGOV-2511

Assessment performed against `dev` @ `e683781f8`, using `drupal/upgrade_status` 4.3.10
and composer dependency resolution. All version and constraint claims below were
verified against the package metadata, not assumed.

## Scope of this document

This is the preparation and planning phase. It records what the upgrade requires,
what is already known to break, and the order of operations. No core bump, contrib
bump, or database change has been performed.

## Preparation already committed

The following landed as part of upgrade prep and are the only changes so far:

- `drupal/upgrade_status` added to `require-dev`.
- `patches/drupal/upgrade_status-add-setfile.patch` — new.
- `composer.lock` updated accordingly.

The `upgrade_status` patch is required, not incidental. Version 4.3.10 calls
`DeprecationMessage::setFile()`, which does not exist on the class, so
`drush upgrade_status:analyze` fatals on any extension whose Twig templates attach
a deprecated library. The patch adds the missing setter. Both the module and the
patch should be removed once the upgrade is complete; the fix is also worth
submitting upstream.

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

Remaining custom-code items, none of which block the upgrade:

| Item | Count | Notes |
| --- | --- | --- |
| `core_version_requirement` capped at `^10` | 26 `.info.yml` files | Mechanical. Blocks install, not code. |
| `DependencySerializationTrait` with `private` properties | 8 files | Genuine D11 risk — private properties do not survive serialization. Change `private` to `protected`. |
| Twig `spaceless` filter | 1 — `node--state-directory-record--full.html.twig:48` | Deprecated in Twig 3.12, removed in Twig 4. |
| `wizardstep` library missing extension name | 1 template | Needs `themename/libraryname` form. |
| phpstan findings in `usagov_directories/utility/*.php` | ~12 | Standalone CLI scripts, not Drupal runtime. Pre-existing, unrelated to D11. |
| Duplicate array keys — `StaticImageSyncCommands.php:438,440` | 2 | Real bug, pre-existing, unrelated to D11. |

### Files with `private` properties under `DependencySerializationTrait`

- `usa_orphaned_entities/src/Form/OrphanedEntitiesSettings.php`
- `usagov_benefit_category_search/src/Form/BenefitCategorySearchForm.php`
- `usagov_directories/src/Form/DirectoryRecordsAddAcronymsForm.php`
- `usagov_directories/src/Form/DirectoryRecordsAddSynonymsForm.php`
- `usagov_directories/src/Form/DirectoryRecordsAddTogglesForm.php`
- `usagov_login/src/Form/LoginSettingsForm.php`
- `usagov_menus/src/Plugin/Block/MobileMenuBlock.php`
- `usagov_ssg_postprocessing/src/Form/ToggleStaticSiteGeneration.php`

## Contrib upgrades required

Twelve modules currently block `drupal/core` 11.4.7. All have D11-compatible
releases available.

| Module | Current | Target | Core constraint at target | Risk |
| --- | --- | --- | --- | --- |
| `address` | 1.12.0 | 2.0.4 | `^9.5 \|\| ^10 \|\| ^11` | major |
| `content_lock` | 2.4.0 | 3.0.0 | `^10.2 \|\| ^11` | major, plus patch rewrite |
| `ctools` | 3.15.0 | 4.1.1 | `^9.5 \|\| ^10 \|\| ^11` | major |
| `faqfield` | 7.1.0 | 8.0.1 | `^8 \|\| ^9 \|\| ^10 \|\| ^11` | major |
| `paragraphs_entity_embed` | 3.0.1 | 4.0.0 | `^10.3 \|\| ^11` | major |
| `uswds_base` | 2.15.0 | 3.12.1 | `^10 \|\| ^11` | major, theme |
| `log_stdout` | 1.5.0 | 3.0.0 | `^8.8 \|\| ^9 \|\| ^10 \|\| ^11` | major ×2 |
| `node_menus` | 3.0.0 | `3.x-dev` | `^10 \| ^11 \|\| ^12` | no stable D11 release |
| `field_defaults` | 2.0.0 | 2.1.1 | `^10.0 \|\| ^11.0` | low |
| `image_style_warmer` | 1.2.0 | 1.3.0 | `^9 \|\| ^10 \|\| ^11` | low |
| `new_relic_rpm` | 2.1.1 | 2.3.0 | `^10.1 \|\| ^11` | low |
| `s3fs` | 3.10.0 | 3.11.0 | `>=11.4.3 < 11.5` | low |

### `node_menus` has no stable D11 release

Only `3.x-dev` declares `^11`. The project already sets `minimum-stability: dev`,
so this resolves, but it means shipping a dev branch of a module that is
load-bearing for menu behaviour here — it is tied to the `correctActiveTrail` core
patch and the `usagov_menus` custom module. **Open decision:** accept the dev
branch, pin to a specific commit, or fork.

### `s3fs` has a narrow, gapped constraint

`>=8.8 <10.7 || >=11.0 <11.2 || >=11.2.3 <11.4.0 || >=11.4.3 <11.5`. Core 11.4.7
falls in the final window, so it resolves. This module has historically lagged core
point releases, and it will constrain which core patch versions can be taken.

## Patch status

Three of nine referenced patches need work.

| Patch | Package | Status |
| --- | --- | --- |
| `correctActiveTrail_d10.patch` | core | **Fails** — 2 of 5 hunks. Rebase required. |
| `viewsSuggestions-10_3.patch` | core | **Fails** — 2 hunks. Rebase required. |
| `content_lock-preview-button-lock-release.patch` | content_lock | **Fails** — both hunks. Rewrite required. |
| `correctMenuChildren_d10.patch` | views_menu_children_filter | OK — package version unchanged. |
| `autologout-multitab-timeout-fix.patch` | autologout | OK — version unchanged. |
| `tomeAggregationFix.patch` | tome | OK — version unchanged. |
| `deduplicateTomeInvokePaths.patch` | tome | OK — version unchanged. |
| `tomePathCountFixes.patch` | tome | OK — version unchanged. |
| 2 × redis patches (remote URLs) | redis | OK — version unchanged. |

### `correctActiveTrail_d10.patch`

Context drift only; the patch logic itself is still correct. D11 restructured
`MenuActiveTrail::getActiveLink()` — it added `<front>` handling via
`pathMatcher->isFrontPage()`, and "select the first matching link" became "select
the first *enabled* matching link" using a loop rather than `reset()`.
`MenuTreeStorage::loadByRoute()` now ends with
`fetchAllAssoc('id', FetchAs::Associative)` in place of the old `\PDO::FETCH_ASSOC`.

**Open decision:** D11 now makes two `loadLinksByRoute()` calls. It must be decided
whether the `['mlid' => 'ASC']` sort should also apply to the `<front>` call.

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
2. **Rebase patches** against 11.4.7 *before* changing any `composer.json`
   constraints. Verify each with `patch --dry-run` against fetched core and module
   sources. A failing patch aborts the entire composer run, so this must be clean
   first.
3. **Bump `core_version_requirement`** to include `^11` across all 26 `.info.yml`
   files.
4. **Change the 8 `private` properties to `protected`** in classes using
   `DependencySerializationTrait`.
5. **Bump `composer.json`** — the three core packages to `11.4.7`, plus the twelve
   contrib constraints.
6. **`bin/composer update`.** Expect to iterate on transitive conflicts; Symfony
   6.4 → 7.4 underneath core is a large jump.
7. **`drush updb`**, then **`drush cr`**.
8. **`drush config:export`.** Expect churn from the six major contrib bumps. Review
   the diff rather than committing wholesale.
9. **Verify.** `composer phpstan`, `composer php-lint`, `composer phpcs-errors`,
   then the Cypress e2e suite in `automated_tests/e2e-cypress`.
10. **Visual QA** of the theme, concentrating on anything `usagov` does not
    override.
11. **Re-run `upgrade_status`** against D11 to confirm a clean board, then remove
    `upgrade_status` and its patch.

Steps 2–5 are pure code and reversible by git. Step 6 onward touches the lockfile
and the database; the snapshot from step 1 is the undo.

## Anticipated problems

- **Symfony 6.4 → 7.4** under core will surface conflicts in packages that are not
  on the blocker list but carry loose Symfony constraints — `onelogin/php-saml`,
  `phpoffice/phpspreadsheet`, `predis/predis`, and the Drush command classes in
  `usagov_ssg_postprocessing`.
- **Config schema churn** from `address` 1→2 and `ctools` 3→4; both have had config
  schema changes across those majors. Field storage for address fields is the first
  thing to check.
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
2. In the rebased `correctActiveTrail` patch, should the `['mlid' => 'ASC']` sort
   also apply to the new `<front>` `loadLinksByRoute()` call?
