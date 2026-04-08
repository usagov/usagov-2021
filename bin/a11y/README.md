# USAGov Accessibility Audit bin

Section 508 / WCAG 2.0-2.1 Level A & AA audit tooling for USAGov HTML content. Parses rendered `index.html` files with BeautifulSoup and reports violations by category.

The 2018 Section 508 refresh (36 CFR Part 1194, §E205.4) requires web content to conform to WCAG 2.0 Level A and AA. This tooling checks WCAG 2.1 (a superset of 2.0), so all findings apply to both standards.

## Requirements

- Python 3.10+
- `beautifulsoup4` and `lxml`

```bash
# from repo root — install if needed
python3 -m venv .venv
source .venv/bin/activate
pip install beautifulsoup4 lxml
```

## bin

| Script | Purpose |
|--------|---------|
| `audit_a11y.py` | Scans HTML files and writes raw results to `json/` |
| `generate_report.py` | Runs the audit (or uses cached results) and writes a markdown report to `reports/` |

In normal use you only need `generate_report.py` — it calls `audit_a11y.py` automatically.

---

## Usage

All commands assume you are at the **repo root** and using the repo venv.

### Run a full audit and generate a report

```bash
# Start the python virtual env
source .venv/bin/activate

# Scan html/blog/ only
python3 bin/a11y/generate_report.py blog/

# Scan html/money/ only
python3 bin/a11y/generate_report.py money/

# Scan all of html/
python3 bin/a11y/generate_report.py
```

Each run produces two output files with matching timestamps and a path slug:

```
bin/a11y/json/2026-03-12T1603_blog.json   ← raw issue data
bin/a11y/reports/2026-03-12T1603_blog.md  ← formatted markdown report
```

### Skip re-scanning (use cached JSON)

```bash
python3 bin/a11y/generate_report.py --cached blog/
```

Picks up the newest `json/*_blog.json` file and writes a fresh timestamped report without re-running the audit. Useful for reformatting the report without waiting for a full scan.

### Override the output path

```bash
python3 bin/a11y/generate_report.py --out my-report.md blog/
```

The JSON still goes to `json/` with its normal timestamped name; only the markdown destination is overridden.

### Run the audit script directly

```bash
# Writes json/TIMESTAMP_blog.json
python3 bin/a11y/audit_a11y.py blog/

# Writes json/TIMESTAMP_all.json
python3 bin/a11y/audit_a11y.py

# Override JSON output path
python3 bin/a11y/audit_a11y.py blog/ --out-json /tmp/my-results.json
```

---

## Output

### `json/` — raw results

Each JSON file is a dictionary keyed by issue category, where each value is a list of issue objects:

```json
{
  "ambiguous_link": [
    { "file": "html/blog/2024/01/some-post/index.html", "detail": "here" },
    ...
  ],
  "duplicate_id": [ ... ]
}
```

### `reports/` — markdown reports

Each report contains:

- **Summary table** — all issue categories with WCAG criterion, conformance level (A/AA), severity, instance count, and file count
- **Per-issue sections** — WCAG criterion, Level, Section 508 provision, description, root cause, recommended fix, and affected files
- **Methodology notes** — Section 508/WCAG standards coverage, scope, and limitations of the static analysis

---

## What is checked

✅ = Required by Section 508 (36 CFR 1194 §E205.4 via WCAG 2.0)
⚠️ = WCAG 2.1-only — not directly mandated by Section 508, but best practice

| Category | WCAG Criterion | Level | 508 | Severity |
|----------|---------------|-------|-----|----------|
| Audio element — transcript unverifiable | 1.2.1 Audio-only (Prerecorded) | A | ✅ | Manual Review |
| Video (native) without captions | 1.2.2 Captions (Prerecorded) | A | ✅ | Critical |
| Embedded video — captions unverifiable | 1.2.2 / 1.2.3 | A/AA | ✅ | Manual Review |
| Video without audio description track | 1.2.5 Audio Description (Prerecorded) | AA | ✅ | High |
| Images missing `alt` | 1.1.1 Non-text Content | A | ✅ | Critical |
| Empty or uninformative `alt` text | 1.1.1 Non-text Content | A | ✅ | High |
| SVG `role="img"` without accessible name | 1.1.1 / 4.1.2 | A | ✅ | Critical |
| Emoji without accessible label | 1.1.1 Non-text Content | A | ✅ | Medium |
| Conflicting `role="img"` + `aria-hidden` on SVGs | 1.1.1 / 4.1.2 | A | ✅ | High |
| Tables without `<th>` or `<caption>` | 1.3.1 Info and Relationships | A | ✅ | Critical |
| Form inputs without labels | 1.3.1 / 3.3.2 | A/AA | ✅ | Critical |
| Fake headings via inline `font-size` | 1.3.1 Info and Relationships | A | ✅ | High |
| Heading level skips | 1.3.1 Info and Relationships | A | ✅ | High |
| Empty or missing headings | 1.3.1 / 2.4.6 | A/AA | ✅ | High |
| Multiple `<h1>` in article body | 1.3.1 / 2.4.6 | A/AA | ✅ | High |
| Single-item lists (possible layout misuse) | 1.3.1 Info and Relationships | A | ✅ | Warning |
| Inputs collecting personal data without `autocomplete` | 1.3.5 Identify Input Purpose | AA | ⚠️ | Medium |
| Color used as sole visual indicator (inline styles) | 1.4.1 Use of Color | A | ✅ | Warning |
| Missing skip navigation link | 2.4.1 Bypass Blocks | A | ✅ | High |
| Missing or generic page `<title>` | 2.4.2 Page Titled | A | ✅ | Critical/High |
| Ambiguous link text ("here", "read more") | 2.4.4 Link Purpose | AA | ✅ | High |
| Raw URL used as link text | 2.4.4 Link Purpose | AA | ✅ | Medium |
| Links without accessible names | 2.4.4 / 4.1.2 | A/AA | ✅ | Critical |
| Heading hierarchy (no `<h1>` in main content) | 2.4.6 Headings and Labels | AA | ✅ | High |
| Missing `lang` attribute on `<html>` | 3.1.1 Language of Page | A | ✅ | Critical |
| Links opening new window without warning | 3.2.2 On Input | A | ✅ | Medium |
| Duplicate `id` attributes | 4.1.1 Parsing | A | ✅ | High |
| Buttons without accessible names | 4.1.2 Name, Role, Value | A | ✅ | Critical |
| Iframes without titles | 4.1.2 Name, Role, Value | A | ✅ | Critical/High |
| Embedded YouTube/Vimeo without iframe title | 4.1.2 Name, Role, Value | A | ✅ | Critical |
| Invalid or unrecognized ARIA roles | 4.1.2 Name, Role, Value | A | ✅ | High |
| `aria-labelledby` referencing non-existent ID | 4.1.2 Name, Role, Value | A | ✅ | High |
| Unquoted `aria-label` on alert banner | 4.1.2 Name, Role, Value | A | ✅ | High |

## What is NOT checked (requires manual review)

- Actual rendered color contrast ratios (requires a browser + color picker tooling)
- Video captions and transcripts for embedded YouTube/Vimeo iframes
- Audio transcript availability — `<audio>` elements are flagged but content cannot be verified statically
- Keyboard focus order and focus visibility
- JavaScript-driven dynamic content
- CMS-managed media and `<picture>`/`srcset` images
