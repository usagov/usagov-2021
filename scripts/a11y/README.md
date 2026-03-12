# USAGov Accessibility Audit Scripts

Static WCAG 2.1 AA audit tooling for USAGov HTML content. Parses rendered `index.html` files with BeautifulSoup and reports violations by category.

## Requirements

- Python 3.10+
- `beautifulsoup4` and `lxml` (already installed in the repo venv)

```bash
# from repo root — install if needed
.venv/bin/pip install beautifulsoup4 lxml
```

## Scripts

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
# Scan html/blog/ only
.venv/bin/python3 scripts/a11y/generate_report.py blog/

# Scan html/money/ only
.venv/bin/python3 scripts/a11y/generate_report.py money/

# Scan all of html/
.venv/bin/python3 scripts/a11y/generate_report.py
```

Each run produces two output files with matching timestamps and a path slug:

```
scripts/a11y/json/2026-03-12T1603_blog.json   ← raw issue data
scripts/a11y/reports/2026-03-12T1603_blog.md  ← formatted markdown report
```

### Skip re-scanning (use cached JSON)

```bash
.venv/bin/python3 scripts/a11y/generate_report.py --cached blog/
```

Picks up the newest `json/*_blog.json` file and writes a fresh timestamped report without re-running the audit. Useful for reformatting the report without waiting for a full scan.

### Override the output path

```bash
.venv/bin/python3 scripts/a11y/generate_report.py --out my-report.md blog/
```

The JSON still goes to `json/` with its normal timestamped name; only the markdown destination is overridden.

### Run the audit script directly

```bash
# Writes json/TIMESTAMP_blog.json
.venv/bin/python3 scripts/a11y/audit_a11y.py blog/

# Writes json/TIMESTAMP_all.json
.venv/bin/python3 scripts/a11y/audit_a11y.py

# Override JSON output path
.venv/bin/python3 scripts/a11y/audit_a11y.py blog/ --out-json /tmp/my-results.json
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

- **Summary table** — all issue categories with WCAG criteria, severity, instance count, and file count
- **Per-issue sections** — description, root cause, recommended fix, and affected files
- **Methodology notes** — scope and limitations of the static analysis

---

## What is checked

| Category | WCAG Criterion | Severity |
|----------|---------------|----------|
| Unquoted `aria-label` on alert banner | 4.1.2 | High |
| Conflicting `role="img"` + `aria-hidden` on SVGs | 1.1.1 / 4.1.2 | High |
| Tables without `<th>` or `<caption>` | 1.3.1 | Critical |
| Multiple `<h1>` in article body | 1.3.1 / 2.4.6 | High |
| Fake headings via inline `font-size` | 1.3.1 | High |
| Heading level skips | 1.3.1 | High |
| Ambiguous link text ("here", "read more") | 2.4.4 | High |
| Duplicate `id` attributes | 4.1.1 | High |
| Images missing `alt` | 1.1.1 | Critical |
| Empty or uninformative `alt` text | 1.1.1 | High |
| Links without accessible names | 2.4.4 / 4.1.2 | Critical |
| Missing `lang` attribute | 3.1.1 | Critical |
| Color contrast (inline styles only) | 1.4.3 | Warning |
| Iframes without titles | 4.1.2 | High |

## What is NOT checked (requires manual review)

- Actual rendered color contrast (requires a browser + color picker)
- Video captions and transcripts (embedded YouTube/Vimeo iframes)
- Keyboard focus order and focus visibility
- JavaScript-driven dynamic content
- CMS-managed media and `<picture>`/`srcset` images
