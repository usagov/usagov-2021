#!/usr/bin/env python3
"""WCAG 2.1 AA audit report generator for USAGov HTML content.

Runs audit_a11y.py to produce fresh results (or uses cached JSON with
--cached), then writes a timestamped markdown report to scripts/a11y/reports/.
JSON results land in scripts/a11y/json/ with matching timestamp + slug names.

Usage:
    python3 generate_report.py              # scan all of html/
    python3 generate_report.py blog/        # scan html/blog/ only
    python3 generate_report.py money/       # scan html/money/ only
    python3 generate_report.py --cached blog/   # skip re-running audit
    python3 generate_report.py --out my.md      # override markdown output path
"""

import argparse
import json
import subprocess
import sys
from datetime import date, datetime
from pathlib import Path

SCRIPT_DIR = Path(__file__).parent
AUDIT_SCRIPT = SCRIPT_DIR / "audit_a11y.py"


# ── Helpers ───────────────────────────────────────────────────────────────────

def make_slug(target: str) -> str:
    """Convert a target path like 'blog/' to a filesystem-safe slug like 'blog'."""
    return target.strip("/").replace("/", "-") or "all"


def run_audit(python_bin: str, target: str, slug: str, json_path: Path) -> dict:
    json_path.parent.mkdir(parents=True, exist_ok=True)
    print(f"Running audit for '{target or 'html/ (all)'}' ...")
    cmd = [python_bin, str(AUDIT_SCRIPT)]
    if target:
        cmd.append(target)
    cmd += ["--out-json", str(json_path)]
    result = subprocess.run(cmd, capture_output=False, text=True)
    if result.returncode != 0:
        sys.exit(1)
    with open(json_path) as f:
        return json.load(f)


def load_cached(slug: str) -> dict:
    json_dir = SCRIPT_DIR / "json"
    candidates = sorted(json_dir.glob(f"*_{slug}.json")) if json_dir.exists() else []
    if not candidates:
        print(
            f"No cached JSON for slug '{slug}' in {json_dir}. Run without --cached first.",
            file=sys.stderr,
        )
        sys.exit(1)
    latest = candidates[-1]  # lexicographic sort on ISO timestamps gives newest last
    print(f"Using cached results: {latest}")
    with open(latest) as f:
        return json.load(f)


def unique_files(items: list) -> list:
    return sorted(set(item["file"] for item in items))


SEVERITY_ORDER = ["Critical", "High", "Medium", "Warning", "Manual Review"]


def severity_key(sev: str) -> int:
    try:
        return SEVERITY_ORDER.index(sev)
    except ValueError:
        return len(SEVERITY_ORDER)


def file_list_md(files: list, threshold: int = 10) -> str:
    lines = "\n".join(f"- {f}" for f in files)
    if len(files) <= threshold:
        return lines
    return (
        f"<details>\n<summary>All {len(files)} files (click to expand)</summary>\n\n"
        f"{lines}\n\n</details>"
    )


def detail_list_md(items: list) -> str:
    lines = []
    for item in items:
        f = item.get("file", "")
        detail = item.get("detail") or item.get("text") or item.get("element") or ""
        if detail:
            detail_str = str(detail)[:120].replace("\n", " ")
            lines.append(f"- **{f}**  \n  `{detail_str}`")
        else:
            lines.append(f"- {f}")
    return "\n".join(lines)


# ── Issue metadata ─────────────────────────────────────────────────────────────
#
# Each entry defines how a group of result keys is rendered.
# merge_keys: one or more keys from a11y_results.json to combine into this section.

ISSUE_METADATA = [
    {
        "key": "unquoted_aria_label_alert",
        "label": "Unquoted `aria-label` on Alert Banner Component",
        "merge_keys": ["unquoted_aria_label", "alert_unquoted_aria_label", "svg_unquoted_aria_label"],
        "wcag": "4.1.2 Name, Role, Value",
        "severity": "High",
        "scope": "Template-level (USWDS alert component in Drupal)",
        "description": """\
The USWDS info alert banner renders two elements with malformed, unquoted `aria-label` attributes:

```html
<div class="usa-alert usa-alert--info" role="region" aria-label=info>
  ...
  <svg role="img" aria-label=info focusable="false">
```

The value `info` is not quoted, making it invalid HTML. Browsers may parse this inconsistently —
some may treat `aria-label=info` as an attribute with no value, effectively leaving the landmark
with no accessible name. The `role="region"` landmark is therefore anonymous (violates 4.1.2 —
regions must have an accessible name to be exposed to AT as landmarks), and the `<svg role="img">`
has no proper accessible name.""",
        "root_cause": (
            "The alert component Twig template in Drupal renders `aria-label=info` without quotes."
        ),
        "fix": """\
In the Drupal template that renders the alert banner, change:

```html
aria-label=info
```

to:

```html
aria-label="Information"
```

Apply to both the wrapping `div[role="region"]` and the `svg[role="img"]` inside it.
Use a meaningful label such as `"Site notice"` or `"Information"` rather than `"info"`.""",
    },
    {
        "key": "role_img_no_name",
        "label": "Conflicting `role=\"img\"` and `aria-hidden=\"true\"` on Pagination SVGs",
        "merge_keys": ["role_img_no_name"],
        "wcag": "1.1.1 Non-text Content / 4.1.2 Name, Role, Value",
        "severity": "High",
        "scope": "Template-level (blog index/pagination pages)",
        "description": """\
On blog listing/pagination pages, SVG icons in the pagination component carry contradictory ARIA
attributes:

```html
<svg aria-hidden="true" class="usa-icon" role="img">
```

`aria-hidden="true"` tells AT to ignore the element entirely. `role="img"` declares it as an
image to AT. These directly conflict. These are decorative chevron/arrow icons; the correct fix
is to remove `role="img"`.""",
        "root_cause": "Template-level inconsistency in the USWDS pagination component rendered by Drupal.",
        "fix": """\
In the Drupal template for the blog pagination component, change:

```html
<svg aria-hidden="true" class="usa-icon" role="img">
```

to:

```html
<svg aria-hidden="true" focusable="false" class="usa-icon">
```""",
    },
    {
        "key": "table_issues",
        "label": "Data Tables Without Header Cells (`<th>`) or Caption",
        "merge_keys": ["table_no_headers", "table_no_caption"],
        "wcag": "1.3.1 Info and Relationships",
        "severity": "Critical",
        "scope": "Content-level",
        "description": """\
One or more `<table>` elements use only `<td>` cells — no `<th>` header cells and no `<caption>`.
Screen readers announce the table and its cell count but cannot tell users what each column or row
represents without header markup.""",
        "root_cause": "Tables migrated from HubSpot/WordPress without semantic header markup.",
        "fix": """\
Add `<th scope="col">` or `<th scope="row">` cells for headers and a `<caption>` element:

```html
<table>
  <caption>Description of the table</caption>
  <thead>
    <tr>
      <th scope="col">Column A</th>
      <th scope="col">Column B</th>
    </tr>
  </thead>
  <tbody>...</tbody>
</table>
```""",
    },
    {
        "key": "multiple_h1",
        "label": "Multiple `<h1>` Elements in Article Body Content",
        "merge_keys": ["multiple_h1"],
        "wcag": "1.3.1 Info and Relationships / 2.4.6 Headings and Labels",
        "severity": "High",
        "scope": "Content-level",
        "description": """\
Several blog posts use `<h1>` tags as section subheadings within article body content. Each page
already has a correct `<h1>` for the post title. Extra `<h1>` elements create a flat heading
hierarchy that is confusing to screen reader users navigating by headings. They should be `<h2>`
or `<h3>`.

The in-content `<h1>` tags carry inline styles such as
`style="font-size: 20px; color: #000000;"` or `style="line-height: 1.5;"`, indicating they were
produced by a WYSIWYG/rich-text editor.""",
        "root_cause": (
            "CMS rich-text editor (HubSpot source posts migrated to Drupal) allowed authors to apply "
            "H1 formatting to section headers within article body content."
        ),
        "fix": """\
Change in-content `<h1>` tags to `<h2>` and remove migration-artifact inline styles:

```html
<!-- Before -->
<h1 style="font-size: 20px; color: #000000;">Study overview</h1>

<!-- After -->
<h2>Study overview</h2>
```""",
    },
    {
        "key": "fake_heading",
        "label": "Fake Section Headings via Inline `font-size` Style",
        "merge_keys": ["fake_heading"],
        "wcag": "1.3.1 Info and Relationships",
        "severity": "High",
        "scope": "Content-level",
        "description": """\
Several blog posts use `<span style="font-size: 20px;">` inside `<p>` tags as visually styled
subheadings. These look like section headers to sighted users but have no heading semantics —
invisible to AT users navigating by headings and to search engines.

```html
<p><span style="font-size: 20px; color: #000000;">Section Title</span></p>
```""",
        "root_cause": (
            "WYSIWYG/rich-text editor (HubSpot or similar) where authors applied large font-size "
            "formatting instead of actual heading levels during content creation or migration."
        ),
        "fix": """\
Replace `<p><span style="font-size:...">` with a semantic heading:

```html
<!-- Before -->
<p><span style="font-size: 20px; color: #000000;">Section Title</span></p>

<!-- After -->
<h2>Section Title</h2>
```""",
    },
    {
        "key": "heading_skip",
        "label": "Heading Level Skip",
        "merge_keys": ["heading_skip"],
        "wcag": "1.3.1 Info and Relationships",
        "severity": "High",
        "scope": "Content-level",
        "description": """\
One or more blog posts jump heading levels (e.g., `<h2>` directly to `<h4>`), skipping an
intermediate level. Heading levels must not skip, as this implies a hierarchical relationship
that doesn't exist and confuses AT users navigating by headings.""",
        "root_cause": "Content edited without following heading hierarchy conventions.",
        "fix": "Correct the heading level to maintain a sequential hierarchy (h1 → h2 → h3 → h4).",
    },
    {
        "key": "ambiguous_link",
        "label": "Ambiguous Link Text",
        "merge_keys": ["ambiguous_link"],
        "wcag": "2.4.4 Link Purpose (In Context)",
        "severity": "High",
        "scope": "Content-level",
        "description": """\
One or more links use non-descriptive text such as "here", "read more", or "click here" with no
additional context via `aria-label` or `title`. Screen reader users navigating by links hear only
the ambiguous word with no indication of the destination.""",
        "root_cause": "Content authored without following link text best practices.",
        "fix": """\
Use descriptive link text, or supplement with `aria-label`:

```html
<!-- Before -->
<a href="...">here</a>

<!-- After -->
<a href="..." aria-label="Read about the USAGov contact center">here</a>
```""",
    },
    {
        "key": "duplicate_id",
        "label": "Duplicate `id` Attributes",
        "merge_keys": ["duplicate_id"],
        "wcag": "4.1.1 Parsing",
        "severity": "High",
        "scope": "Template-level",
        "description": """\
One or more pages contain duplicate HTML `id` values. The HTML spec requires IDs to be unique
within a document. Duplicate IDs cause AT to behave unpredictably when using landmarks, form
labels, skip links, or `aria-labelledby`/`aria-describedby` references.

The duplicates are caused by the template rendering both a mobile menu and a desktop
search/navigation form in the same HTML document, each using the same set of IDs.""",
        "root_cause": "Drupal theme renders mobile and desktop nav/search forms with identical IDs.",
        "fix": (
            "Differentiate IDs between the mobile and desktop versions of the navigation/search "
            "components, or apply `aria-hidden=\"true\"` on the visually hidden version so AT only "
            "encounters the active one."
        ),
    },
    {
        "key": "emoji_no_label",
        "label": "Emoji Used Without Accessible Label (📅 Date Display)",
        "merge_keys": ["emoji_no_label"],
        "wcag": "1.1.1 Non-text Content",
        "severity": "Medium",
        "scope": "Template-level (post date display in blog listing/index pages)",
        "description": """\
The calendar emoji used to indicate a post's publication date is rendered without any accessible
wrapper:

```html
<span>📅 October 28, 2015</span>
```

Screen readers announce the emoji using its Unicode name (e.g., "spiral calendar"), which is
unexpected and verbose. The date text already conveys the information.""",
        "root_cause": "Drupal theme code that renders the post date in blog listing/index pages.",
        "fix": """\
Hide the emoji from AT (the date text already provides the information):

```html
<span><span aria-hidden="true">📅</span> October 28, 2015</span>
```

Or wrap it with a proper role and label:

```html
<span><span role="img" aria-label="Published">📅</span> October 28, 2015</span>
```""",
    },
    {
        "key": "new_window_no_warning",
        "label": "Links Opening in New Window Without User Warning",
        "merge_keys": ["new_window_no_warning"],
        "wcag": "3.2.2 On Input",
        "severity": "Medium",
        "scope": "Content-level",
        "description": """\
Links with `target="_blank"` open in a new tab or window without providing any advance notice to
the user. Screen reader and keyboard users have no way to anticipate the context change.

```html
<a href="..." target="_blank" rel="noopener">link text</a>
```""",
        "root_cause": "Content authors used `target=\"_blank\"` without adding new-window indicators.",
        "fix": """\
Add `(opens in new tab)` as visible or screen-reader-only text, or use `aria-label`:

```html
<a href="..." target="_blank" rel="noopener"
   aria-label="Link text (opens in new tab)">Link text</a>
```""",
    },
    {
        "key": "url_link_text",
        "label": "Raw URL Used as Link Text",
        "merge_keys": ["url_link_text"],
        "wcag": "2.4.4 Link Purpose (In Context)",
        "severity": "Medium",
        "scope": "Content-level",
        "description": """\
One or more links use a full URL as their visible link text. Raw URLs are verbose when read by
screen readers (each path segment is announced separately) and are not meaningful descriptors
of the destination.

```html
<a href="https://www.usa.gov/buying-home-programs">https://www.usa.gov/buying-home-programs</a>
```""",
        "root_cause": "Content pasted from another source without converting the URL to descriptive text.",
        "fix": """\
Replace the URL with a descriptive label:

```html
<a href="https://www.usa.gov/buying-home-programs">Home buying programs (USA.gov)</a>
```""",
    },
    {
        "key": "inline_color_style",
        "label": "Inline `color`/`background-color` Styles (Potential Contrast Issue)",
        "merge_keys": ["inline_color_style"],
        "wcag": "1.4.1 Use of Color / 1.4.3 Contrast (Minimum)",
        "severity": "Warning",
        "scope": "Content-level — migration artifacts",
        "description": """\
Multiple blog posts contain inline style attributes setting foreground or background colors:

```html
<span style="color: #0e101a;">text</span>
<span style="background-color: transparent;">text</span>
```

These are artifacts of content migrated from HubSpot/WordPress. Custom hex colors may not meet
the 4.5:1 contrast ratio required at WCAG AA. Each unique color combination needs manual or
automated contrast verification against its rendered background.""",
        "root_cause": "WYSIWYG editor in HubSpot/WordPress preserved inline color formatting during CMS migration.",
        "fix": (
            "Remove all inline color `style` attributes from content body text. "
            "Verify no content relies on color alone to convey meaning. "
            "Spot-check any non-black/non-transparent hex values against the page background "
            "for contrast compliance (minimum 4.5:1 ratio)."
        ),
    },
]

# Fallback severity for any result categories not listed in ISSUE_METADATA
GENERIC_SEVERITY = {
    "missing_alt": "Critical",
    "empty_alt_in_content": "Warning",
    "filename_as_alt": "High",
    "non_descriptive_alt": "High",
    "empty_link": "Critical",
    "empty_heading": "High",
    "iframe_no_title": "Critical",
    "iframe_generic_title": "High",
    "table_th_no_scope": "High",
    "missing_lang": "Critical",
    "input_no_label": "Critical",
    "input_placeholder_only": "High",
    "aria_labelledby_bad_ref": "High",
    "invalid_role": "High",
    "svg_role_img_no_name": "Critical",
    "svg_icon_link_no_name": "Critical",
    "video_iframe_no_title": "Critical",
    "video_needs_caption_review": "Manual Review",
    "video_no_captions": "Critical",
    "no_skip_nav": "High",
    "missing_page_title": "Critical",
    "generic_page_title": "High",
    "button_no_name": "Critical",
    "single_item_list": "Warning",
    "missing_autocomplete": "Medium",
    "abbr_no_title": "Medium",
    "no_h1": "High",
}


# ── Report builder ─────────────────────────────────────────────────────────────

def build_report(results: dict, audit_date: str, target: str = "") -> str:
    lines = []

    # Build sections: merge result keys per ISSUE_METADATA entry
    consumed_keys: set = set()
    sections = []

    for meta in ISSUE_METADATA:
        merged_items = []
        for k in meta["merge_keys"]:
            if k in results:
                merged_items.extend(results[k])
                consumed_keys.add(k)
        if not merged_items:
            continue
        sections.append((meta, merged_items))

    # Catch-all for any result keys not covered by ISSUE_METADATA
    for key, items in sorted(results.items()):
        if key in consumed_keys or not items:
            continue
        sev = GENERIC_SEVERITY.get(key, "Warning")
        generic_meta = {
            "key": key,
            "label": key.replace("_", " ").title(),
            "merge_keys": [key],
            "wcag": "See audit script",
            "severity": sev,
            "scope": "Unknown",
            "description": f"**{len(items)} instance(s)** detected. Review `a11y_results.json` for details.",
            "root_cause": "",
            "fix": "",
        }
        sections.append((generic_meta, items))

    # Sort by severity
    sections.sort(key=lambda s: severity_key(s[0]["severity"]))

    total_files = len({item["file"] for _, items in sections for item in items})
    total_issues = sum(len(items) for _, items in sections)

    # ── Header ──
    scan_label = f"`html/{target.strip('/')}`" if target else "`html/` (all)"
    slug = make_slug(target)
    lines += [
        f"# WCAG 2.1 AA Accessibility Audit — {scan_label}",
        "",
        f"**Audit date:** {audit_date}  ",
        f"**Files scanned:** all `index.html` files under {scan_label}  ",
        f"**Audit script:** `scripts/a11y/audit_a11y.py`  ",
        f"**Results data:** `scripts/a11y/json/TIMESTAMP_{slug}.json`",
        "",
        "---",
        "",
    ]

    # ── Summary table ──
    lines += [
        "## Summary",
        "",
        "| # | Issue | WCAG | Severity | Instances | Files |",
        "|---|-------|------|----------|-----------|-------|",
    ]
    for i, (meta, items) in enumerate(sections, 1):
        n_files = len(set(item["file"] for item in items))
        lines.append(
            f"| {i} | {meta['label']} | {meta['wcag']} "
            f"| **{meta['severity']}** | {len(items)} | {n_files} |"
        )
    lines += [
        "",
        f"**Total issues: {total_issues} across {total_files} files.**",
        "",
        "---",
        "",
    ]

    # ── Per-issue sections ──
    for i, (meta, items) in enumerate(sections, 1):
        files = unique_files(items)
        n_files = len(files)
        n_items = len(items)

        lines += [
            f"## Issue {i} — {meta['label']}",
            "",
            f"**WCAG:** {meta['wcag']}  ",
            f"**Severity:** {meta['severity']}  ",
            f"**Scope:** {meta['scope']}  ",
            f"**Instances:** {n_items}  ",
            f"**Files affected:** {n_files}",
            "",
        ]

        if meta.get("description"):
            lines += ["### Description", "", meta["description"], ""]

        if meta.get("root_cause"):
            lines += ["### Root Cause", "", meta["root_cause"], ""]

        if meta.get("fix"):
            lines += ["### Recommended Fix", "", meta["fix"], ""]

        lines += ["### Affected Files", ""]

        # Show per-instance detail for small sets; file list otherwise
        if n_items <= 15:
            lines.append(detail_list_md(items))
        else:
            lines.append(file_list_md(files))

        lines += ["", "---", ""]

    # ── Methodology ──
    lines += [
        "## Methodology Notes",
        "",
        "- **Tools used:** Python 3 with BeautifulSoup4 and lxml",
        "- **Audit script:** `scripts/a11y/audit_a11y.py`",
        "- **What was NOT checked (requires manual review):**",
        "  - Actual color contrast ratios for inline-styled text — requires rendering + color picker tooling",
        "  - Video captions/transcripts — embedded YouTube/Vimeo iframes require manual review",
        "  - Keyboard focus order and focus visibility (requires browser testing)",
        "  - Dynamic/JavaScript-driven content (static HTML may not reflect full rendered DOM)",
        "  - CMS-managed media and `<picture>`/`srcset` images",
        "",
    ]

    return "\n".join(lines)


def main():
    parser = argparse.ArgumentParser(
        description="Generate WCAG audit report for USAGov HTML content"
    )
    parser.add_argument(
        "target", nargs="?", default="",
        help="Subdirectory of html/ to scan (e.g. blog/). Defaults to all of html/."
    )
    parser.add_argument(
        "--cached", action="store_true",
        help="Use the newest matching JSON in scripts/a11y/json/ instead of re-running the audit"
    )
    parser.add_argument(
        "--out", default=None,
        help="Override markdown output path (default: scripts/a11y/reports/TIMESTAMP_SLUG.md)"
    )
    parser.add_argument(
        "--python", default=sys.executable,
        help="Python interpreter to use when running the audit"
    )
    args = parser.parse_args()

    slug = make_slug(args.target)
    ts = datetime.now().strftime("%Y-%m-%dT%H%M")
    json_path = SCRIPT_DIR / "json" / f"{ts}_{slug}.json"

    results = load_cached(slug) if args.cached else run_audit(args.python, args.target, slug, json_path)

    audit_date = date.today().isoformat()
    report = build_report(results, audit_date, args.target)

    if args.out is not None:
        out_path = Path(args.out)
    else:
        out_dir = SCRIPT_DIR / "reports"
        out_dir.mkdir(exist_ok=True)
        out_path = out_dir / f"{ts}_{slug}.md"

    out_path.write_text(report, encoding="utf-8")
    print(f"Report written to {out_path}")


if __name__ == "__main__":
    main()
