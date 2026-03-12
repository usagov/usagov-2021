#!/usr/bin/env python3
"""
WCAG 2.1 Level AA Accessibility Audit Script for USAGov HTML Content
Analyzes all index.html files under a target directory and reports violations.

Usage:
    python3 audit_a11y.py              # scans all of html/
    python3 audit_a11y.py blog/        # scans html/blog/ only
    python3 audit_a11y.py money/       # scans html/money/ only
"""

import argparse
import os
import re
import sys
from datetime import datetime
from pathlib import Path
from bs4 import BeautifulSoup, Tag
from collections import defaultdict

# ── helpers ────────────────────────────────────────────────────────────────────

def relative_path(filepath: Path) -> str:
    return str(filepath).replace(str(Path(__file__).parent.parent.parent) + "/", "")

def extract_content_area(soup):
    """Return only the main content area, excluding nav/header/footer boilerplate."""
    main = soup.find("main") or soup.find(id="main-content")
    if main:
        return main
    return soup.find("body") or soup

def get_article_body(soup):
    """Return the div that contains the actual blog post body text."""
    # Drupal field body
    body = soup.find(class_=re.compile(r"field--name-body"))
    if body:
        return body
    article = soup.find("article", class_="blog-post")
    if article:
        return article
    return extract_content_area(soup)

# ── issue collectors ───────────────────────────────────────────────────────────

def check_images(soup, filepath, issues):
    """WCAG 1.1.1 - Images must have descriptive alt text."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for img in content.find_all("img"):
        # Skip images inside nav, header, footer
        if img.find_parent(["nav", "header", "footer"]):
            continue

        alt = img.get("alt")
        aria_hidden = img.get("aria-hidden")
        src = img.get("src", "")

        # Known decorative template images - skip
        decorative_srcs = [
            "sprite.svg", "Favicon", "USAGov_logo", "USAGov_Logo",
            "search--dark", "usa-icons", "icon-dot-gov", "icon-https",
            "Facebook_Icon", "X_Twitter_Icon", "Email_Icon",
            "share-icon", "USAGov_logo_51px", "USAGov_Logo_80px",
            "favicon-57.png"
        ]
        if any(d in src for d in decorative_srcs):
            continue

        if aria_hidden == "true":
            continue

        if alt is None:
            issues["missing_alt"].append({
                "file": rel,
                "element": str(img)[:200],
                "wcag": "1.1.1",
                "severity": "Critical"
            })
        elif alt.strip() == "":
            # Empty alt is fine for decorative, but flag if inside article content
            parent_article = img.find_parent(class_=re.compile(r"field--name-body|blog-post"))
            if parent_article:
                issues["empty_alt_in_content"].append({
                    "file": rel,
                    "element": str(img)[:200],
                    "wcag": "1.1.1",
                    "severity": "Warning"
                })
        else:
            # Check for filename-as-alt (bad practice)
            if re.match(r'^[\w\-]+\.(jpg|jpeg|png|gif|svg|webp)$', alt.strip(), re.I):
                issues["filename_as_alt"].append({
                    "file": rel,
                    "element": str(img)[:200],
                    "wcag": "1.1.1",
                    "severity": "High"
                })
            # Check for redundant alt like "image", "photo", "picture", "graphic"
            if re.match(r'^(image|photo|picture|graphic|icon|logo|banner|screenshot)s?$', alt.strip(), re.I):
                issues["non_descriptive_alt"].append({
                    "file": rel,
                    "element": str(img)[:200],
                    "wcag": "1.1.1",
                    "severity": "High"
                })


def check_links(soup, filepath, issues):
    """WCAG 2.4.4 - Link purpose must be clear from text or context."""
    rel = relative_path(filepath)
    content = get_article_body(soup)
    if content is None:
        return

    ambiguous_patterns = re.compile(
        r'^(click here?|here|read more|learn more|more info(rmation)?|'
        r'this link|this page|this article|this post|this site|'
        r'see more|view more|find out more|details|link|continue|'
        r'click|tap here?|go here?|more\.|more\.{3})$',
        re.I
    )

    for a in content.find_all("a", href=True):
        # Skip nav links
        if a.find_parent(["nav", "header", "footer", "aside"]):
            continue

        text = a.get_text(strip=True)
        aria_label = a.get("aria-label", "").strip()
        aria_labelledby = a.get("aria-labelledby", "").strip()

        effective_label = aria_label or text

        if not effective_label and not aria_labelledby:
            # Check for img with alt inside
            img_inside = a.find("img")
            if img_inside and img_inside.get("alt", "").strip():
                continue
            issues["empty_link"].append({
                "file": rel,
                "element": str(a)[:200],
                "wcag": "2.4.4 / 4.1.2",
                "severity": "Critical"
            })
        elif ambiguous_patterns.match(effective_label):
            issues["ambiguous_link"].append({
                "file": rel,
                "element": str(a)[:200],
                "context": text,
                "wcag": "2.4.4",
                "severity": "High"
            })


def check_headings(soup, filepath, issues):
    """WCAG 1.3.1 / 2.4.6 - Heading structure must be logical and non-empty."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    headings = content.find_all(["h1", "h2", "h3", "h4", "h5", "h6"])

    # Check for empty headings
    for h in headings:
        if h.find_parent(["nav", "header", "footer"]):
            continue
        text = h.get_text(strip=True)
        aria_label = h.get("aria-label", "").strip()
        aria_labelledby = h.get("aria-labelledby", "").strip()
        if not text and not aria_label and not aria_labelledby:
            issues["empty_heading"].append({
                "file": rel,
                "element": str(h)[:200],
                "wcag": "1.3.1 / 2.4.6",
                "severity": "High"
            })

    # Check heading hierarchy in main content only
    main = soup.find("main") or soup.find(id="main-content")
    if not main:
        return

    main_headings = [(int(h.name[1]), h) for h in main.find_all(["h1","h2","h3","h4","h5","h6"])
                     if not h.find_parent(["nav", "aside"])]

    prev_level = 1
    for level, h in main_headings:
        if level > prev_level + 1:
            issues["heading_skip"].append({
                "file": rel,
                "element": str(h)[:200],
                "detail": f"Skipped from h{prev_level} to h{level}: '{h.get_text(strip=True)[:60]}'",
                "wcag": "1.3.1",
                "severity": "High"
            })
        prev_level = level


def check_iframes(soup, filepath, issues):
    """WCAG 4.1.2 - iframes must have a title attribute."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for iframe in content.find_all("iframe"):
        title = iframe.get("title", "").strip()
        aria_label = iframe.get("aria-label", "").strip()
        aria_labelledby = iframe.get("aria-labelledby", "").strip()

        if not title and not aria_label and not aria_labelledby:
            issues["iframe_no_title"].append({
                "file": rel,
                "element": str(iframe)[:200],
                "wcag": "4.1.2",
                "severity": "Critical"
            })
        elif title.lower() in ["", "iframe", "embed", "frame"]:
            issues["iframe_generic_title"].append({
                "file": rel,
                "element": str(iframe)[:200],
                "wcag": "4.1.2",
                "severity": "High"
            })


def check_tables(soup, filepath, issues):
    """WCAG 1.3.1 - Tables must have proper headers and captions."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for table in content.find_all("table"):
        if table.find_parent(["nav", "header", "footer"]):
            continue

        has_th = bool(table.find("th"))
        has_caption = bool(table.find("caption"))
        has_summary = table.get("summary", "").strip()
        has_aria_label = table.get("aria-label", "").strip()
        has_aria_labelledby = table.get("aria-labelledby", "").strip()

        if not has_th:
            issues["table_no_headers"].append({
                "file": rel,
                "element": str(table)[:300],
                "wcag": "1.3.1",
                "severity": "Critical"
            })

        if not has_caption and not has_summary and not has_aria_label and not has_aria_labelledby:
            issues["table_no_caption"].append({
                "file": rel,
                "element": str(table)[:300],
                "wcag": "1.3.1",
                "severity": "High"
            })

        # Check for scope on th cells
        for th in table.find_all("th"):
            if not th.get("scope") and not th.get("id"):
                issues["table_th_no_scope"].append({
                    "file": rel,
                    "element": str(th)[:200],
                    "wcag": "1.3.1",
                    "severity": "High"
                })


def check_language(soup, filepath, issues):
    """WCAG 3.1.1 / 3.1.2 - Page language must be set; language changes must be marked."""
    rel = relative_path(filepath)
    html_tag = soup.find("html")

    if not html_tag:
        return

    lang = html_tag.get("lang", "").strip()
    if not lang:
        issues["missing_lang"].append({
            "file": rel,
            "element": "<html>",
            "wcag": "3.1.1",
            "severity": "Critical"
        })


def check_forms(soup, filepath, issues):
    """WCAG 1.3.1 / 3.3.2 - Form inputs must have associated labels."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for inp in content.find_all(["input", "select", "textarea"]):
        if inp.find_parent(["nav", "header", "footer"]):
            continue
        inp_type = inp.get("type", "text").lower()
        if inp_type in ["hidden", "submit", "button", "image", "reset"]:
            continue

        inp_id = inp.get("id", "")
        aria_label = inp.get("aria-label", "").strip()
        aria_labelledby = inp.get("aria-labelledby", "").strip()
        title = inp.get("title", "").strip()
        placeholder = inp.get("placeholder", "").strip()

        # Check for explicit label
        has_label = False
        if inp_id:
            label = content.find("label", attrs={"for": inp_id})
            if label and label.get_text(strip=True):
                has_label = True
        # Check if wrapped in label
        if inp.find_parent("label"):
            has_label = True

        if not has_label and not aria_label and not aria_labelledby and not title:
            if placeholder:
                # Placeholder alone is not sufficient
                issues["input_placeholder_only"].append({
                    "file": rel,
                    "element": str(inp)[:200],
                    "wcag": "1.3.1 / 3.3.2",
                    "severity": "High"
                })
            else:
                issues["input_no_label"].append({
                    "file": rel,
                    "element": str(inp)[:200],
                    "wcag": "1.3.1 / 3.3.2",
                    "severity": "Critical"
                })


def check_aria(soup, filepath, issues):
    """WCAG 4.1.2 - ARIA attributes must be valid and correctly used."""
    rel = relative_path(filepath)

    # Check for unquoted aria-label (malformed HTML from migration)
    raw_content = filepath.read_text(encoding="utf-8", errors="replace")

    # Pattern: aria-label=word (not aria-label="..." or aria-label='...')
    unquoted = re.findall(r'aria-label=(?!["\'])\S+', raw_content)
    if unquoted:
        for match in set(unquoted):
            issues["unquoted_aria_label"].append({
                "file": rel,
                "element": match,
                "wcag": "4.1.2",
                "severity": "Critical"
            })

    # Check for aria-labelledby referencing non-existent IDs
    for el in soup.find_all(attrs={"aria-labelledby": True}):
        labelledby = el.get("aria-labelledby", "").strip()
        if labelledby:
            for ref_id in labelledby.split():
                if not soup.find(id=ref_id):
                    issues["aria_labelledby_bad_ref"].append({
                        "file": rel,
                        "element": str(el)[:200],
                        "detail": f"aria-labelledby='{labelledby}' references non-existent id='{ref_id}'",
                        "wcag": "4.1.2",
                        "severity": "High"
                    })

    # Check for role="img" without accessible name
    for el in soup.find_all(attrs={"role": "img"}):
        aria_label = el.get("aria-label", "").strip()
        aria_labelledby = el.get("aria-labelledby", "").strip()
        title = el.find("title")
        if not aria_label and not aria_labelledby and not title:
            issues["role_img_no_name"].append({
                "file": rel,
                "element": str(el)[:200],
                "wcag": "1.1.1",
                "severity": "Critical"
            })

    # Check for empty role values or invalid roles
    valid_roles = {
        "alert","alertdialog","application","article","banner","button","cell",
        "checkbox","columnheader","combobox","complementary","contentinfo","definition",
        "dialog","directory","document","feed","figure","form","grid","gridcell",
        "group","heading","img","link","list","listbox","listitem","log","main",
        "marquee","math","menu","menubar","menuitem","menuitemcheckbox","menuitemradio",
        "navigation","none","note","option","presentation","progressbar","radio",
        "radiogroup","region","row","rowgroup","rowheader","scrollbar","search",
        "searchbox","separator","slider","spinbutton","status","switch","tab",
        "table","tablist","tabpanel","term","textbox","timer","toolbar","tooltip",
        "tree","treegrid","treeitem"
    }
    for el in soup.find_all(attrs={"role": True}):
        roles = el.get("role", "").split()
        for role in roles:
            if role and role not in valid_roles:
                issues["invalid_role"].append({
                    "file": rel,
                    "element": str(el)[:200],
                    "detail": f"Invalid role='{role}'",
                    "wcag": "4.1.2",
                    "severity": "High"
                })


def check_svg(soup, filepath, issues):
    """WCAG 1.1.1 / 4.1.2 - SVGs used as images must have accessible names."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for svg in content.find_all("svg"):
        if svg.find_parent(["nav", "header", "footer"]):
            continue

        role = svg.get("role", "")
        aria_hidden = svg.get("aria-hidden", "")
        aria_label = svg.get("aria-label", "").strip()
        aria_labelledby = svg.get("aria-labelledby", "").strip()
        has_title = bool(svg.find("title"))
        focusable = svg.get("focusable", "")

        if aria_hidden == "true":
            continue

        if role == "img":
            if not aria_label and not aria_labelledby and not has_title:
                issues["svg_role_img_no_name"].append({
                    "file": rel,
                    "element": str(svg)[:200],
                    "wcag": "1.1.1",
                    "severity": "Critical"
                })
        elif not aria_hidden and not role:
            # SVG without role or aria-hidden - ambiguous
            # Only flag if it has meaningful content (not just paths)
            # Check if it's a lone SVG used as an icon/image in content
            parent_a = svg.find_parent("a")
            if parent_a:
                a_text = parent_a.get_text(strip=True)
                img_in_a = parent_a.find("img")
                a_aria = parent_a.get("aria-label", "").strip()
                if not a_text and not img_in_a and not a_aria:
                    issues["svg_icon_link_no_name"].append({
                        "file": rel,
                        "element": str(svg)[:200],
                        "wcag": "2.4.4 / 4.1.2",
                        "severity": "Critical"
                    })


def check_videos(soup, filepath, issues):
    """WCAG 1.2.2 / 1.2.3 - Videos must have captions and audio descriptions."""
    rel = relative_path(filepath)
    content = get_article_body(soup)
    if not content:
        return

    # Check for YouTube/Vimeo embedded iframes without proper labeling
    for iframe in content.find_all("iframe"):
        src = iframe.get("src", "") or iframe.get("data-src", "")
        if any(v in src for v in ["youtube.com", "youtu.be", "vimeo.com", "player.vimeo"]):
            title = iframe.get("title", "").strip()
            if not title:
                issues["video_iframe_no_title"].append({
                    "file": rel,
                    "element": str(iframe)[:200],
                    "wcag": "4.1.2",
                    "severity": "Critical"
                })
            # Note: We can't verify captions from static HTML alone, but flag embedded videos
            issues["video_needs_caption_review"].append({
                "file": rel,
                "element": str(iframe)[:200],
                "detail": f"Embedded video (src: {src[:80]}...) - captions/transcript must be verified manually",
                "wcag": "1.2.2 / 1.2.3",
                "severity": "Manual Review"
            })

    # Check <video> elements
    for video in content.find_all("video"):
        tracks = video.find_all("track")
        has_captions = any(t.get("kind") in ["captions", "subtitles"] for t in tracks)
        if not has_captions:
            issues["video_no_captions"].append({
                "file": rel,
                "element": str(video)[:200],
                "wcag": "1.2.2",
                "severity": "Critical"
            })


def check_duplicate_ids(soup, filepath, issues):
    """WCAG 4.1.1 - IDs must be unique on each page."""
    rel = relative_path(filepath)
    all_ids = []
    for el in soup.find_all(id=True):
        all_ids.append(el.get("id"))

    seen = set()
    for id_val in all_ids:
        if id_val in seen:
            issues["duplicate_id"].append({
                "file": rel,
                "element": f"id='{id_val}'",
                "wcag": "4.1.1",
                "severity": "High"
            })
        seen.add(id_val)


def check_skip_nav(soup, filepath, issues):
    """WCAG 2.4.1 - Pages must provide a skip navigation mechanism."""
    rel = relative_path(filepath)
    skip_link = soup.find("a", class_=re.compile(r"skip|skipnav|skip-nav|skip-to", re.I))
    if not skip_link:
        skip_link = soup.find("a", href=re.compile(r"^#(skip|main|content|maincontent)", re.I))

    if not skip_link:
        issues["no_skip_nav"].append({
            "file": rel,
            "element": "N/A",
            "wcag": "2.4.1",
            "severity": "High"
        })


def check_page_title(soup, filepath, issues):
    """WCAG 2.4.2 - Pages must have descriptive titles."""
    rel = relative_path(filepath)
    title = soup.find("title")
    if not title or not title.get_text(strip=True):
        issues["missing_page_title"].append({
            "file": rel,
            "element": "<title>",
            "wcag": "2.4.2",
            "severity": "Critical"
        })
    elif len(title.get_text(strip=True)) < 5:
        issues["generic_page_title"].append({
            "file": rel,
            "element": f"<title>{title.get_text(strip=True)}</title>",
            "wcag": "2.4.2",
            "severity": "High"
        })


def check_buttons(soup, filepath, issues):
    """WCAG 4.1.2 - Buttons must have accessible names."""
    rel = relative_path(filepath)
    content = extract_content_area(soup)

    for btn in content.find_all("button"):
        if btn.find_parent(["nav", "header", "footer"]):
            continue
        text = btn.get_text(strip=True)
        aria_label = btn.get("aria-label", "").strip()
        aria_labelledby = btn.get("aria-labelledby", "").strip()
        has_img = btn.find("img") and btn.find("img").get("alt", "").strip()
        has_svg_title = (svg := btn.find("svg")) and svg.find("title")

        if not text and not aria_label and not aria_labelledby and not has_img and not has_svg_title:
            issues["button_no_name"].append({
                "file": rel,
                "element": str(btn)[:200],
                "wcag": "4.1.2",
                "severity": "Critical"
            })


def check_list_misuse(soup, filepath, issues):
    """WCAG 1.3.1 - Lists must be used for list content, not for layout only."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    # Check for single-item lists (often a misuse)
    for ul in body.find_all(["ul", "ol"]):
        if ul.find_parent(["nav", "header", "footer", "aside"]):
            continue
        items = ul.find_all("li", recursive=False)
        if len(items) == 1:
            issues["single_item_list"].append({
                "file": rel,
                "element": str(ul)[:200],
                "wcag": "1.3.1",
                "severity": "Warning"
            })


def check_color_indicators(soup, filepath, issues):
    """WCAG 1.4.1 - Color must not be the only means of conveying information."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    # Look for inline color styling without other indicators
    for el in body.find_all(style=True):
        style = el.get("style", "").lower()
        if "color" in style and "text-decoration" not in style:
            inner_text = el.get_text(strip=True)
            if inner_text and el.name in ["span", "p", "td", "a"]:
                issues["inline_color_style"].append({
                    "file": rel,
                    "element": str(el)[:200],
                    "detail": f"style='{el.get('style', '')}'",
                    "wcag": "1.4.1",
                    "severity": "Warning"
                })


def check_target_blank(soup, filepath, issues):
    """WCAG 3.2.2 - Links opening in new windows should warn users."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    for a in body.find_all("a", target="_blank"):
        aria_label = a.get("aria-label", "")
        title = a.get("title", "")
        text = a.get_text(strip=True)
        # Check if there's a warning in aria-label, title, or text
        warning_words = ["new window", "new tab", "opens in", "external", "nuevamente", "nueva ventana"]
        has_warning = any(w in (aria_label + title + text).lower() for w in warning_words)
        if not has_warning:
            issues["new_window_no_warning"].append({
                "file": rel,
                "element": str(a)[:200],
                "detail": f"Link text: '{text[:60]}'",
                "wcag": "3.2.2",
                "severity": "Medium"
            })


def check_emoji_in_content(soup, filepath, issues):
    """WCAG 1.1.1 - Emojis without aria-label or role='img' can be read confusingly."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    # Look for emoji characters in text nodes (common in migrated blog content)
    emoji_pattern = re.compile(
        u"[\U0001F600-\U0001F64F\U0001F300-\U0001F5FF\U0001F680-\U0001F6FF"
        u"\U0001F700-\U0001F77F\U0001F900-\U0001F9FF\U00002702-\U000027B0"
        u"\U0001FA00-\U0001FA6F\U0001FA70-\U0001FAFF\U00002600-\U000026FF"
        u"\u2600-\u27BF\U0001F1E0-\U0001F1FF]+"
    )

    for el in body.find_all(string=True):
        if emoji_pattern.search(str(el)):
            parent = el.parent
            if not parent:
                continue
            aria_label = parent.get("aria-label", "")
            role = parent.get("role", "")
            if not (role == "img" and aria_label):
                issues["emoji_no_label"].append({
                    "file": rel,
                    "element": str(str(el).strip()[:100]),
                    "detail": f"Found emoji in <{parent.name}>: '{str(el).strip()[:60]}'",
                    "wcag": "1.1.1",
                    "severity": "Medium"
                })


def check_alert_component(soup, filepath, issues):
    """Check USAGov alert component for specific a11y issues from migration."""
    rel = relative_path(filepath)
    raw = filepath.read_text(encoding="utf-8", errors="replace")

    # Specific known issue: aria-label=info without quotes
    if re.search(r'aria-label=info\b', raw):
        issues["alert_unquoted_aria_label"].append({
            "file": rel,
            "element": "usa-alert div",
            "detail": "aria-label=info (unquoted) - browsers may misparse; should be aria-label=\"info\"",
            "wcag": "4.1.2",
            "severity": "High"
        })

    if re.search(r'<svg[^>]+aria-label=info\b', raw):
        issues["svg_unquoted_aria_label"].append({
            "file": rel,
            "element": "svg with aria-label=info",
            "detail": "SVG has unquoted aria-label=info; should be aria-label=\"info\"",
            "wcag": "4.1.2 / 1.1.1",
            "severity": "High"
        })


def check_figcaption(soup, filepath, issues):
    """WCAG 1.1.1 - figures with images should have figcaptions where appropriate."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    for fig in body.find_all("figure"):
        imgs = fig.find_all("img")
        figcap = fig.find("figcaption")
        if imgs and not figcap:
            # Only flag if images don't have descriptive alt (informational images without caption)
            for img in imgs:
                alt = img.get("alt", "").strip()
                if alt and len(alt) < 100:
                    # Has informational alt but no visible caption - acceptable, just note
                    pass


def check_heading_count(soup, filepath, issues):
    """Warn if a page has no h1 in main content, or multiple h1s."""
    rel = relative_path(filepath)
    main = soup.find("main") or soup.find(id="main-content")
    if not main:
        return

    h1s = [h for h in main.find_all("h1") if not h.find_parent(["nav", "aside"])]
    if len(h1s) == 0:
        issues["no_h1"].append({
            "file": rel,
            "element": "<main>",
            "wcag": "1.3.1 / 2.4.6",
            "severity": "High"
        })
    elif len(h1s) > 1:
        issues["multiple_h1"].append({
            "file": rel,
            "element": f"{len(h1s)} h1 elements found",
            "wcag": "1.3.1",
            "severity": "Medium"
        })


def check_abbr_acronym(soup, filepath, issues):
    """WCAG 3.1.4 - Abbreviations should include expansions."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    # Look for <abbr> without title
    for abbr in body.find_all("abbr"):
        if not abbr.get("title", "").strip():
            issues["abbr_no_title"].append({
                "file": rel,
                "element": str(abbr)[:200],
                "wcag": "3.1.4",
                "severity": "Medium"
            })


def check_url_link_text(soup, filepath, issues):
    """WCAG 2.4.4 - Raw URLs should not be used as link text."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    for a in body.find_all("a", href=True):
        text = a.get_text(strip=True)
        if re.match(r"https?://", text) and len(text) > 20:
            issues["url_link_text"].append({
                "file": rel,
                "element": str(a)[:200],
                "text": text[:100],
                "wcag": "2.4.4",
                "severity": "Medium"
            })


def check_fake_headings(soup, filepath, issues):
    """WCAG 1.3.1 - Large inline font-size on non-heading elements used as visual headings."""
    rel = relative_path(filepath)
    body = get_article_body(soup)
    if not body:
        return

    for el in body.find_all(style=True):
        style = el.get("style", "")
        if "font-size" in style and el.name in ["p", "span", "div", "b", "strong"]:
            size_match = re.search(r"font-size:\s*(\d+)(px|pt|em|rem)", style)
            if size_match:
                size = int(size_match.group(1))
                unit = size_match.group(2)
                if (unit == "px" and size >= 20) or (unit == "pt" and size >= 15):
                    txt = el.get_text(strip=True)[:80]
                    if txt:
                        issues["fake_heading"].append({
                            "file": rel,
                            "element": f"<{el.name} style=\"{style[:80]}\">: {txt}",
                            "wcag": "1.3.1",
                            "severity": "High"
                        })


def check_autocomplete(soup, filepath, issues):
    """WCAG 1.3.5 - Inputs collecting personal data need autocomplete."""
    rel = relative_path(filepath)
    main = soup.find("main") or soup.find(id="main-content")
    if not main:
        return

    personal_data_types = {
        "name": ["name", "full name", "your name"],
        "email": ["email", "e-mail"],
        "tel": ["phone", "telephone", "mobile"],
        "street-address": ["address", "street"],
        "postal-code": ["zip", "postal", "postcode"],
    }

    for inp in main.find_all("input"):
        if inp.find_parent(["nav", "header", "footer"]):
            continue
        label_text = ""
        inp_id = inp.get("id", "")
        if inp_id:
            lbl = main.find("label", attrs={"for": inp_id})
            if lbl:
                label_text = lbl.get_text(strip=True).lower()

        placeholder = inp.get("placeholder", "").lower()
        name_attr = inp.get("name", "").lower()
        combined = label_text + " " + placeholder + " " + name_attr

        for autocomplete_val, keywords in personal_data_types.items():
            if any(kw in combined for kw in keywords):
                if not inp.get("autocomplete"):
                    issues["missing_autocomplete"].append({
                        "file": rel,
                        "element": str(inp)[:200],
                        "detail": f"Input collecting '{autocomplete_val}' lacks autocomplete attribute",
                        "wcag": "1.3.5",
                        "severity": "Medium"
                    })


# ── main audit loop ─────────────────────────────────────────────────────────────

def audit_file(filepath: Path, issues: dict):
    try:
        content = filepath.read_text(encoding="utf-8", errors="replace")
        soup = BeautifulSoup(content, "lxml")
    except Exception as e:
        issues["parse_error"].append({
            "file": relative_path(filepath),
            "element": str(e),
            "wcag": "N/A",
            "severity": "Error"
        })
        return

    check_language(soup, filepath, issues)
    check_page_title(soup, filepath, issues)
    check_skip_nav(soup, filepath, issues)
    check_headings(soup, filepath, issues)
    check_heading_count(soup, filepath, issues)
    check_images(soup, filepath, issues)
    check_links(soup, filepath, issues)
    check_iframes(soup, filepath, issues)
    check_tables(soup, filepath, issues)
    check_forms(soup, filepath, issues)
    check_aria(soup, filepath, issues)
    check_svg(soup, filepath, issues)
    check_videos(soup, filepath, issues)
    check_duplicate_ids(soup, filepath, issues)
    check_buttons(soup, filepath, issues)
    check_alert_component(soup, filepath, issues)
    check_target_blank(soup, filepath, issues)
    check_emoji_in_content(soup, filepath, issues)
    check_color_indicators(soup, filepath, issues)
    check_abbr_acronym(soup, filepath, issues)
    check_autocomplete(soup, filepath, issues)
    check_url_link_text(soup, filepath, issues)
    check_fake_headings(soup, filepath, issues)


def main(scan_dir: Path):
    files = sorted(scan_dir.rglob("index.html"))
    print(f"Auditing {len(files)} files under {scan_dir}...")

    issues = defaultdict(list)

    for i, f in enumerate(files):
        if i % 50 == 0:
            print(f"  Progress: {i}/{len(files)} files processed...")
        audit_file(f, issues)

    print(f"\nDone! Issues found by category:")
    total = 0
    for cat, lst in sorted(issues.items()):
        print(f"  {cat}: {len(lst)}")
        total += len(lst)
    print(f"  TOTAL: {total}")

    return issues


if __name__ == "__main__":
    import json

    _SCRIPT_DIR = Path(__file__).parent
    _ts_default = datetime.now().strftime("%Y-%m-%dT%H%M")

    _parser = argparse.ArgumentParser(
        description="WCAG 2.1 AA accessibility audit for USAGov HTML content"
    )
    _parser.add_argument(
        "target", nargs="?", default="",
        help="Subdirectory of html/ to scan (e.g. blog/). Defaults to all of html/."
    )
    _parser.add_argument(
        "--out-json", default=None,
        help="Override JSON output path (default: scripts/a11y/json/TIMESTAMP_SLUG.json)"
    )
    _args = _parser.parse_args()

    REPO_ROOT = Path(__file__).parent.parent.parent
    HTML_DIR = REPO_ROOT / "html"

    if _args.target:
        scan_dir = HTML_DIR / _args.target.strip("/")
        slug = _args.target.strip("/").replace("/", "-")
    else:
        scan_dir = HTML_DIR
        slug = "all"

    if not scan_dir.exists():
        print(f"Error: Directory not found: {scan_dir}", file=sys.stderr)
        sys.exit(1)

    if _args.out_json:
        output_path = Path(_args.out_json)
    else:
        output_path = _SCRIPT_DIR / "json" / f"{_ts_default}_{slug}.json"

    output_path.parent.mkdir(parents=True, exist_ok=True)

    issues = main(scan_dir)

    with open(output_path, "w") as f:
        json.dump(dict(issues), f, indent=2, default=str)
    print(f"\nRaw results written to {output_path}")
