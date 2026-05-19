# Accessifiers

Accessifiers are Drush `php:script` files that automatically fix WCAG / Section 508 accessibility issues in Drupal content. They are designed to be run after an accessibility audit (see [bin/a11y/README.md](../../../bin/a11y/README.md)) identifies issues that can be corrected programmatically in the database.

Each accessifier targets a specific content type (e.g., `blog.php` → `blog_post` nodes). All scripts support a safe `--dry-run` mode and make no permanent changes unless explicitly told to.

---

## Prerequisites

- Docker containers must be running (`docker compose up -d` or equivalent)
- The `cms` container must be healthy (`docker ps`)
- Run from the repository root

---

## Available Scripts

| Script | Content Type | Issues Fixed |
|---|---|---|
| `blog.php` | `blog_post` | Heading hierarchy, inline color/font-size styles, new-tab link warnings, ambiguous link text, table headers/captions, dev-domain hrefs |

---

## Usage

### General syntax

```bash
bin/drush php:script scripts/a11y/accessifiers/<SCRIPT>.php
bin/drush php:script scripts/a11y/accessifiers/<SCRIPT>.php -- --dry-run
bin/drush php:script scripts/a11y/accessifiers/<SCRIPT>.php -- --dry-run --verbose
```

### Flags

| Flag | Description |
|---|---|
| _(none)_ | **Live run** — writes changes to the database |
| `--dry-run` | Runs all fix logic but does not save anything; prints which nodes would change |
| `--verbose` | With `--dry-run`, also prints nodes that would be left unchanged |

### Recommended workflow

1. **Audit first** — generate a report to see what issues exist and their counts:
   ```bash
   source .venv/bin/activate
   python3 bin/a11y/generate_report.py blog/
   ```

2. **Dry run** — verify the accessifier detects the expected issues:
   ```bash
   bin/drush php:script scripts/a11y/accessifiers/blog.php -- --dry-run
   ```
   Review the printed stats. Confirm counts roughly match the audit report.

3. **Live run** — apply fixes to the database:
   ```bash
   bin/drush php:script scripts/a11y/accessifiers/blog.php
   ```

4. **Regenerate static HTML** — push changes through Tome:
   ```bash
   bin/static-site
   ```

5. **Re-audit** — confirm issues are resolved:
   ```bash
   python3 bin/a11y/generate_report.py blog/
   ```

---

## blog.php — Fix Details

Targets all `blog_post` nodes. Applies eight fixes in dependency order.

### Fix 1 — Styled `<span>`s inside headings (WCAG 1.3.1)

WYSIWYG editors sometimes wrap heading text in a `<span style="font-size: ...; color: ...">`.
The span is semantically meaningless and its inline styles override the accessible theme.
**Resolution:** Unwrap `<span style>` elements found directly inside any `<h1>`–`<h6>`, preserving the text content.

_Run before Fix 3 so the entire span is removed rather than leaving a bare `<span>` with only `font-size` remaining._

### Fix 2 — Heading hierarchy (WCAG 1.3.1, 2.4.6)

The Drupal theme renders the site name as `<h1>` and the node title as `<h2>`. Body content must start at `<h3>` and must not skip levels.
**Resolution:**
1. Calculate the minimum heading level in the body and shift all headings up so the first is `<h3>`.
2. Make a second pass to collapse any remaining skips (e.g., `<h3>` → `<h5>` → capped to `<h4>`).

### Fix 3 — Inline color / background-color styles (WCAG 1.4.1, 1.4.3)

CKEditor paste artifacts leave `color:` and `background-color:` inline styles on `<span>`, `<p>`, `<td>`, and other elements. These override the accessible stylesheet and can fail contrast requirements.
**Resolution:** Strip only `color` and `background-color` from inline `style` attributes; preserve other properties (e.g., `text-align`). Bare `<span>` elements with no remaining attributes are also unwrapped.

### Fix 4 — Fixed font size (WCAG 1.4.4)

CKEditor paste artifacts can leave fixed `font-size` declarations on body content. These bypass the responsive theme typography and can prevent text from resizing cleanly.
**Resolution:** Strip only `font-size` from inline `style` attributes; preserve other properties.

### Fix 5 — New-tab warnings (WCAG 3.2.2, 2.4.4)

Links with `target="_blank"` must warn users before opening a new context.
**Resolution:** Add `aria-label="[visible text] (opens in a new tab)"` to every `<a target="_blank">` that does not already carry a "new tab" or "new window" phrase in its label or visible text.

### Fix 6 — Ambiguous link text (WCAG 2.4.4)

Links with generic text like "here", "click here", "read more", etc. are meaningless to screen-reader users who navigate by link.
**Resolution:** Add an `aria-label` derived from the URL slug (or Drupal node title via path alias lookup) to any link whose visible text matches a known ambiguous term.

### Fix 7 — Table headers and captions (WCAG 1.3.1)

Data tables without `<th>` elements prevent screen readers from conveying column relationships. Tables without a `<caption>` have no accessible name.
**Resolution:**
- Promote every `<td>` in the first row to `<th scope="col">` for tables that have no existing `<th>`.
- Add a `<caption>` derived from the nearest preceding heading in the content (falls back to `"Data table N"`).

_Only tables whose first row has ≥ 2 non-empty cells are treated; layout tables are skipped._

### Fix 8 — Raw-URL link text (WCAG 2.4.4)

Links whose visible text is verbatim the `href` URL string (e.g., `https://www.usa.gov/some-page`) are not useful descriptions for screen-reader users who navigate by link.

**Resolution:** Replace raw-URL visible text with a human-readable label derived from the node title (via Drupal path alias lookup) or a title-cased URL slug as a fallback.

---

## Known non-content issues

The following issue types are **not addressed** by any accessifier because they originate in the Drupal theme template, not in node body content:

| Issue | Source | Resolution |
|---|---|---|
| `missing_lang` | `<html>` template | Resolved after a successful Tome regeneration run |
| `no_skip_nav` | Theme layout template | Resolved after a successful Tome regeneration run |

---

## Writing a new accessifier

1. Create a new PHP file in this directory named after the content type (e.g., `news.php`).
2. Follow the same pattern as `blog.php`:
   - Parse `$extra` for `--dry-run` / `--verbose`.
   - Keep a `$stats` array and increment it inside each fix function.
   - Use `a11y_parse()` / `a11y_serialize()` helpers for DOM manipulation.
   - Apply fixes in a deterministic order, one function per fix.
   - Print a summary at the end.
3. Test with `--dry-run` before running live.
4. Update the table in this README with the new script.
