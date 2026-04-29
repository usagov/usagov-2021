<?php

/**
 * @file
 * blog.php
 *
 * One-time WCAG 2.1 AA accessibility fixer for blog_post content.
 *
 * Targets issues found by scripts/a11y/audit_a11y.py as of 2026-03-13:
 *
 *   inline_color_style (250)     WCAG 1.4.1  Strip color/background-color inline styles
 *   inline_font_size (281)       WCAG 1.4.4  Strip fixed font-size inline styles
 *   new_window_no_warning (27)   WCAG 3.2.2  Add "(opens in a new tab)" aria-label
 *   fake_heading (15)            WCAG 1.3.1  Unwrap styled <span>s inside heading tags
 *   multiple_h1 / heading_skip   WCAG 1.3.1  Normalize heading hierarchy (body min h3)
 *   table_no_headers/caption (2) WCAG 1.3.1  Promote first-row <td>s to <th scope="col">
 *                                             and add <caption> from surrounding context
 *   url_link_text (4)            WCAG 2.4.4  Fix raw-URL visible link text
 *   ambiguous_link (1)           WCAG 2.4.4  Add descriptive aria-label to "here" link
 *
 * Page-structure issues NOT addressed here (not content issues):
 *   missing_lang (3) / no_skip_nav (3) — these 3 pages have legacy/stale HTML that
 *   will resolve after a successful Tome regeneration run. The <html lang> attribute
 *   and skip-nav link are injected by the Drupal theme template, not by content.
 *
 * Run:
 *   bin/drush php:script scripts/a11y/accessifiers/blog.php
 *   bin/drush php:script scripts/a11y/accessifiers/blog.php -- --dry-run
 *   bin/drush php:script scripts/a11y/accessifiers/blog.php -- --dry-run --verbose
 *
 * After verifying with --dry-run, run live, then:
 *   1. bin/static-site           (regenerate HTML via Tome)
 *   2. source .venv/bin/activate && python3 scripts/a11y/generate_report.py blog/
 */

use Drupal\node\Entity\Node;

// ── CLI options ────────────────────────────────────────────────────────────────

$dry_run = !empty($extra) && in_array('--dry-run', $extra);
$verbose = !empty($extra) && in_array('--verbose', $extra);

echo ($dry_run ? '[DRY RUN] No changes will be saved.' : '[LIVE RUN] Changes will be saved to the database.') . "\n\n";

// ── Stats ──────────────────────────────────────────────────────────────────────

$stats = [
  'nodes_checked'         => 0,
  'nodes_changed'         => 0,
  'inline_color_style'    => 0,
  'inline_font_size'      => 0,
  'new_window_no_warning' => 0,
  'styled_spans_stripped' => 0,
  'headings_normalized'   => 0,
  'tables_fixed'          => 0,
  'url_link_text'         => 0,
  'ambiguous_link'        => 0,
];

// ── DOM helpers ────────────────────────────────────────────────────────────────

/**
 * Parse an HTML fragment into a DOMDocument.
 *
 * Returns [$doc, $wrap] where $wrap is the <div id="_a11y_wrap_"> container.
 * Using a wrapper div lets us use getElementById() and serialize cleanly.
 */
function a11y_parse(string $html): array {
  $doc = new DOMDocument('1.0', 'UTF-8');
  libxml_use_internal_errors(TRUE);
  $doc->loadHTML(
    '<?xml encoding="UTF-8">'
    . '<html><head><meta charset="UTF-8"></head><body>'
    . '<div id="_a11y_wrap_">' . $html . '</div>'
    . '</body></html>'
  );
  libxml_clear_errors();
  libxml_use_internal_errors(FALSE);
  $doc->encoding = 'UTF-8';
  $wrap = $doc->getElementById('_a11y_wrap_');
  return [$doc, $wrap];
}

/**
 * Serialize the children of the wrapper div back to an HTML string.
 *
 * PHP DOMDocument may encode non-ASCII characters as numeric HTML entities
 * (e.g. ó → &#243;). We convert those back to their UTF-8 characters so
 * that the database content stays clean and human-readable.
 */
function a11y_serialize(DOMDocument $doc, DOMNode $wrap): string {
  $parts = [];
  foreach ($wrap->childNodes as $child) {
    $parts[] = $doc->saveHTML($child);
  }
  $html = implode('', $parts);

  // Decode numeric codepoints > 127 produced by DOMDocument.
  // Structural ASCII entities (&amp; &lt; &gt; etc.) are not numeric so they
  // are unaffected.
  $html = preg_replace_callback('/&#(\d+);/', static function (array $m): string {
    $cp = (int) $m[1];
    return $cp > 127 ? mb_chr($cp, 'UTF-8') : $m[0];
  }, $html);
  $html = preg_replace_callback('/&#x([0-9a-fA-F]+);/', static function (array $m): string {
    $cp = hexdec($m[1]);
    return $cp > 127 ? mb_chr($cp, 'UTF-8') : $m[0];
  }, $html);

  return $html;
}

/**
 * Replace $el with its own children in the DOM (unwrap, keep content).
 */
function dom_unwrap(DOMElement $el): void {
  $parent = $el->parentNode;
  if (!$parent) {
    return;
  }
  while ($el->firstChild) {
    $parent->insertBefore($el->firstChild, $el);
  }
  $parent->removeChild($el);
}

/**
 * Replace heading $old with a new element of level $new_level,
 * preserving attributes and children.
 */
function dom_rename_heading(DOMDocument $doc, DOMElement $old, int $new_level): DOMElement {
  $new_el = $doc->createElement('h' . $new_level);
  foreach ($old->attributes as $attr) {
    $new_el->setAttribute($attr->name, $attr->value);
  }
  while ($old->firstChild) {
    $new_el->appendChild($old->firstChild);
  }
  $old->parentNode->replaceChild($new_el, $old);
  return $new_el;
}

/**
 * Split a CSS style string into [property => value] map.
 */
function css_parse(string $style): array {
  $props = [];
  foreach (explode(';', $style) as $decl) {
    $decl = trim($decl);
    if ($decl === '') {
      continue;
    }
    [$prop, $val] = array_pad(explode(':', $decl, 2), 2, '');
    $props[trim($prop)] = trim($val ?? '');
  }
  return $props;
}

/**
 * Serialize a [property => value] map back to a CSS style string.
 */
function css_serialize(array $props): string {
  $parts = [];
  foreach ($props as $prop => $val) {
    $parts[] = $prop . ': ' . $val;
  }
  return implode('; ', $parts);
}

// ── Fix 1: Unwrap styled <span>s inside heading tags ──────────────────────────
/**
 * Blog editors used the WYSIWYG to style heading text with explicit font-size
 * and color on a <span>, producing e.g.:
 *   <h2><span style="font-size: 20px; color: #000000;">Section title</span></h2>
 *
 * The <span> is semantically meaningless inside a heading and its inline styles
 * override the theme. Strip the <span> wrapper, preserving its text content
 * inside the parent heading.
 *
 * Run this before fix_inline_color_style so the span is removed entirely rather
 * than left as a bare <span> with only font-size remaining.
 *
 * WCAG 1.3.1 — info conveyed by presentation only.
 */
function fix_styled_spans_in_headings(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath = new DOMXPath($doc);
  $changed = FALSE;

  $headings = iterator_to_array(
    $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $wrap)
  );

  foreach ($headings as $h) {
    // Collect in reverse (innermost first) to avoid unwrapping a parent before
    // its child, which would leave the child detached.
    $spans = array_reverse(
      iterator_to_array($xpath->query('.//span[@style]', $h))
    );
    foreach ($spans as $span) {
      if (!$span->parentNode) {
        continue; // already detached by an ancestor unwrap
      }
      dom_unwrap($span);
      $stats['styled_spans_stripped']++;
      $changed = TRUE;
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 2: Normalize heading hierarchy ────────────────────────────────────────
/**
 * Blog body fields must not use <h1> or <h2>. The page template already
 * renders the site name as <h1> and the node title as <h2>. All headings
 * in the body content must start at <h3> and maintain a logical sequence
 * (no skips of more than one level).
 *
 * Algorithm:
 *   1. Find the minimum heading level present in the body.
 *   2. Calculate shift = max(0, 3 - min_level).
 *   3. Apply the shift to every heading (capped at h6).
 *   4. Second pass (in document order): for each heading whose level exceeds
 *      prev_level + 1, cap it at prev_level + 1. This fixes any skips that
 *      remain after step 3. The "previous context" is initialised to h2
 *      since the node title (h2) sits immediately above the body.
 *
 * WCAG 1.3.1, 2.4.6 — info and relationships; headings and labels.
 */
function fix_heading_hierarchy(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath = new DOMXPath($doc);

  $headings = iterator_to_array(
    $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $wrap)
  );

  if (empty($headings)) {
    return $html;
  }

  $old_levels = array_map(static fn($h) => (int) $h->nodeName[1], $headings);
  $min_level  = min($old_levels);
  $shift      = max(0, 3 - $min_level);

  // Pass 1 — apply global shift.
  $new_levels = array_map(static fn($l) => min(6, $l + $shift), $old_levels);

  // Pass 2 — remove remaining skips.
  $prev = 2; // node title context
  foreach ($new_levels as $i => &$new) {
    if ($new > $prev + 1) {
      $new = $prev + 1;
    }
    $prev = $new;
  }
  unset($new);

  $changed = FALSE;
  foreach ($headings as $i => $h) {
    if ($old_levels[$i] !== $new_levels[$i]) {
      dom_rename_heading($doc, $h, $new_levels[$i]);
      $stats['headings_normalized']++;
      $changed = TRUE;
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 3: Strip color / background-color inline styles ───────────────────────
/**
 * CKEditor paste artifacts leave inline color and background-color styles on
 * <span>, <p>, <td>, and other elements. These override the theme's accessible
 * stylesheet and may fail color-contrast requirements. Strip those specific CSS
 * properties only; preserve any other inline styles (e.g., text-align).
 *
 * After stripping, if a <span> has no remaining attributes it is also unwrapped
 * so we don't litter the markup with empty <span> tags.
 *
 * WCAG 1.4.1 — use of color; 1.4.3 — contrast minimum.
 */
function fix_inline_color_style(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath  = new DOMXPath($doc);
  $styled = iterator_to_array($xpath->query('.//*[@style]', $wrap));

  $changed     = FALSE;
  $to_unwrap   = [];

  foreach ($styled as $el) {
    if (!$el->parentNode) {
      continue; // detached during a previous iteration
    }
    $props    = css_parse($el->getAttribute('style'));
    $filtered = array_filter(
      $props,
      static fn($prop) => !in_array(strtolower($prop), ['color', 'background-color'], TRUE),
      ARRAY_FILTER_USE_KEY
    );

    if (count($filtered) === count($props)) {
      continue; // nothing to strip
    }

    $stats['inline_color_style']++;
    $changed = TRUE;

    if (empty($filtered)) {
      $el->removeAttribute('style');
      if ($el->nodeName === 'span' && !$el->hasAttributes()) {
        $to_unwrap[] = $el;
      }
    } else {
      $el->setAttribute('style', css_serialize($filtered));
    }
  }

  // Unwrap bare <span>s bottom-up; array_reverse gives innermost spans first.
  foreach (array_reverse($to_unwrap) as $span) {
    if ($span->parentNode) {
      dom_unwrap($span);
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 4: Strip fixed font-size inline styles ───────────────────────────────
/**
 * CKEditor paste artifacts can leave fixed font-size declarations on body
 * content. These bypass the responsive theme typography and can prevent text
 * from resizing cleanly. Strip font-size only; preserve any other inline styles.
 *
 * After stripping, if a <span> has no remaining attributes it is also unwrapped
 * so we don't litter the markup with empty <span> tags.
 *
 * WCAG 1.4.4 — resize text.
 */
function fix_inline_font_size(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath = new DOMXPath($doc);
  $styled = iterator_to_array($xpath->query('.//*[@style]', $wrap));

  $changed = FALSE;
  $to_unwrap = [];

  foreach ($styled as $el) {
    if (!$el->parentNode) {
      continue; // detached during a previous iteration
    }

    $props = css_parse($el->getAttribute('style'));
    $filtered = array_filter(
      $props,
      static fn($prop) => strtolower($prop) !== 'font-size',
      ARRAY_FILTER_USE_KEY
    );

    if (count($filtered) === count($props)) {
      continue; // nothing to strip
    }

    $stats['inline_font_size']++;
    $changed = TRUE;

    if (empty($filtered)) {
      $el->removeAttribute('style');
      if ($el->nodeName === 'span' && !$el->hasAttributes()) {
        $to_unwrap[] = $el;
      }
    }
    else {
      $el->setAttribute('style', css_serialize($filtered));
    }
  }

  // Unwrap bare <span>s bottom-up; array_reverse gives innermost spans first.
  foreach (array_reverse($to_unwrap) as $span) {
    if ($span->parentNode) {
      dom_unwrap($span);
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 5: Add new-tab warnings to target="_blank" links ─────────────────────
/**
 * Links that open in a new tab must warn users, especially keyboard and
 * screen-reader users who may not expect the context switch. Add
 * aria-label="[visible text] (opens in a new tab)" to every <a target="_blank">
 * that does not already carry such a warning.
 *
 * WCAG 3.2.2 — on input; 2.4.4 — link purpose.
 */
function fix_new_window_no_warning(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath = new DOMXPath($doc);
  $links = iterator_to_array($xpath->query('.//a[@target="_blank"]', $wrap));
  $changed = FALSE;

  foreach ($links as $a) {
    $existing_label = $a->getAttribute('aria-label');

    // Skip if the label or visible text already mentions the tab/window context.
    $warning_present = $existing_label
      && (stripos($existing_label, 'new tab') !== FALSE || stripos($existing_label, 'new window') !== FALSE);
    if ($warning_present) {
      continue;
    }
    $text = trim($a->textContent);
    if (stripos($text, 'new tab') !== FALSE || stripos($text, 'new window') !== FALSE) {
      continue;
    }

    // Build accessible label from existing aria-label or visible text.
    $base  = $existing_label ?: $text;
    $label = trim($base) . ' (opens in a new tab)';
    $a->setAttribute('aria-label', $label);

    $stats['new_window_no_warning']++;
    $changed = TRUE;
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 6: Descriptive aria-label on ambiguous links ─────────────────────────
/**
 * Links whose visible text is a generic word like "here", "click here",
 * "read more", etc. convey no destination to screen-reader users who navigate
 * by link. Add an aria-label derived from the URL slug when no aria-label is
 * already set.
 *
 * WCAG 2.4.4 — link purpose (in context).
 */
function fix_ambiguous_links(string $html, array &$stats): string {
  static $ambiguous = [
    'here', 'click here', 'this link', 'this page', 'this article',
    'read more', 'learn more', 'more info', 'more information',
    'see more', 'view more', 'find out more', 'details', 'link',
    'continue', 'click', 'tap here', 'go here',
  ];

  [$doc, $wrap] = a11y_parse($html);
  $xpath = new DOMXPath($doc);
  $links = iterator_to_array($xpath->query('.//a[@href]', $wrap));
  $changed = FALSE;

  foreach ($links as $a) {
    if ($a->getAttribute('aria-label')) {
      continue; // already labelled
    }
    $text = trim(strtolower($a->textContent));
    if (!in_array($text, $ambiguous, TRUE)) {
      continue;
    }

    $label = a11y_title_from_href($a->getAttribute('href'));
    if ($label) {
      $a->setAttribute('aria-label', $label);
      $stats['ambiguous_link']++;
      $changed = TRUE;
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 7: Table headers and captions ─────────────────────────────────────────
/**
 * Data tables that have no <th> elements are inaccessible to screen-reader
 * users: column relationships cannot be programmatically determined. Tables
 * without a <caption> (or equivalent) have no accessible name.
 *
 * For each table that has no existing <th>:
 *   a) Promote every <td> in the first row to <th scope="col">, preserving
 *      all existing attributes (e.g. style, colspan).
 *   b) Insert a <caption> whose text is derived from the nearest preceding
 *      heading in the content. Falls back to "Data table N" if none is found.
 *
 * Only tables with a header-like first row (i.e., the first row has ≥2 cells,
 * at least one of which is non-empty) are treated. Layout tables are skipped.
 *
 * WCAG 1.3.1 — info and relationships.
 */
function fix_tables(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath   = new DOMXPath($doc);
  $tables  = iterator_to_array($xpath->query('.//table', $wrap));
  $changed = FALSE;
  $table_n = 0;

  foreach ($tables as $table) {
    // Skip if already marked up with <th>.
    if ($xpath->query('.//th', $table)->length > 0) {
      continue;
    }

    $first_row = $xpath->query('.//tr', $table)->item(0);
    if (!$first_row) {
      continue;
    }

    $tds = iterator_to_array($xpath->query('./td', $first_row));
    if (count($tds) < 2) {
      continue; // single-cell rows are not column headers
    }
    // Confirm at least one cell has non-empty text (skip fully-blank header rows
    // only if ALL cells are whitespace/nbsp).
    $has_content = FALSE;
    foreach ($tds as $td) {
      if (preg_replace('/[\s\xc2\xa0]/', '', $td->textContent) !== '') {
        $has_content = TRUE;
        break;
      }
    }
    if (!$has_content) {
      continue;
    }

    $table_n++;

    // a) Promote first-row cells to <th scope="col">.
    foreach ($tds as $td) {
      $th = $doc->createElement('th');
      $th->setAttribute('scope', 'col');
      foreach ($td->attributes as $attr) {
        $th->setAttribute($attr->name, $attr->value);
      }
      while ($td->firstChild) {
        $th->appendChild($td->firstChild);
      }
      $td->parentNode->replaceChild($th, $td);
    }

    // b) Add <caption> from the nearest preceding heading.
    $caption_text = '';
    // Walk preceding siblings then ancestor siblings for a heading element.
    $candidate = $table->previousSibling;
    while ($candidate && !$caption_text) {
      if ($candidate instanceof DOMElement && preg_match('/^h[1-6]$/', $candidate->nodeName)) {
        $caption_text = trim($candidate->textContent);
      }
      $candidate = $candidate->previousSibling;
    }
    if (!$caption_text) {
      $caption_text = 'Data table ' . $table_n;
    }

    $caption = $doc->createElement('caption');
    $caption->appendChild($doc->createTextNode($caption_text));
    $table->insertBefore($caption, $table->firstChild);

    $stats['tables_fixed']++;
    $changed = TRUE;
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Fix 8: Fix raw-URL link text ─────────────────────────────────────────────
/**
 * Links whose visible text is verbatim the href URL string (i.e. text starts
 * with http:// or https://) are not useful descriptions for screen-reader
 * users. Replace the visible text with a slug-derived or node-title-derived
 * label.
 *
 * WCAG 2.4.4 — link purpose (in context).
 */
function fix_url_link_text(string $html, array &$stats): string {
  [$doc, $wrap] = a11y_parse($html);
  $xpath   = new DOMXPath($doc);
  $links   = iterator_to_array($xpath->query('.//a[@href]', $wrap));
  $changed = FALSE;

  foreach ($links as $a) {
    $href = $a->getAttribute('href');
    $text = trim($a->textContent);

    // Raw URL as visible text on any domain.
    if ((strncmp($text, 'https://', 8) === 0 || strncmp($text, 'http://', 7) === 0)
        && $text === $href) {
      $label = a11y_title_from_href($href);
      if ($label) {
        a11y_set_link_text($doc, $a, $label);
        $stats['url_link_text']++;
        $changed = TRUE;
      }
    }
  }

  return $changed ? a11y_serialize($doc, $wrap) : $html;
}

// ── Shared link helpers ────────────────────────────────────────────────────────

/**
 * Given a URL string, return a human-readable label by:
 *   1. Looking up the URL path as a Drupal path alias to get the node title.
 *   2. Falling back to title-casing the URL slug.
 */
function a11y_title_from_href(string $url): string {
  $path = parse_url($url, PHP_URL_PATH);
  if (!$path) {
    return '';
  }

  // Attempt Drupal path-alias lookup (works for internal /blog/... paths).
  try {
    $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    if (preg_match('#^/node/(\d+)$#', $system_path, $m)) {
      $node = Node::load((int) $m[1]);
      if ($node) {
        return $node->getTitle();
      }
    }
  }
  catch (\Exception $e) {
    // Fall through.
  }

  // Fallback: derive a readable label from the last URL path segment.
  $slug = basename(rtrim($path, '/'));
  // Remove leading year/date patterns like 2022-09- from blog slugs.
  $slug = preg_replace('/^\d{4}-\d{2}-/', '', $slug);
  return $slug ? ucwords(str_replace('-', ' ', $slug)) : '';
}

/**
 * Replace the text content of a link while preserving any inline child elements
 * (e.g. <em>, <strong>). When the link contains only text nodes, replaces them
 * directly. When it has mixed content, falls back to an aria-label instead to
 * avoid altering the visual markup.
 */
function a11y_set_link_text(DOMDocument $doc, DOMElement $a, string $label): void {
  // Check for child elements (as opposed to pure text nodes).
  $has_child_elements = FALSE;
  foreach ($a->childNodes as $child) {
    if ($child->nodeType === XML_ELEMENT_NODE) {
      $has_child_elements = TRUE;
      break;
    }
  }

  if ($has_child_elements) {
    // Complex markup inside the link — add aria-label instead.
    $a->setAttribute('aria-label', $label);
  } else {
    // Plain text link — replace text content.
    while ($a->firstChild) {
      $a->removeChild($a->firstChild);
    }
    $a->appendChild($doc->createTextNode($label));
  }
}

// ── Main loop ─────────────────────────────────────────────────────────────────

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'blog_post')
  ->accessCheck(FALSE)
  ->execute();

echo sprintf('Found %d blog_post nodes. Processing...', count($nids)) . "\n";

foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node) {
    continue;
  }

  $body = $node->body->value ?? '';
  if (!$body) {
    continue;
  }

  $stats['nodes_checked']++;
  $original = $body;

  // Apply fixes in dependency order:
  //   1. Strip spans FROM inside headings (before color-strip, so the whole
  //      span is removed rather than leaving a bare font-size span).
  $body = fix_styled_spans_in_headings($body, $stats);
  //   2. Normalize heading levels to min h3.
  $body = fix_heading_hierarchy($body, $stats);
  //   3. Strip remaining color/background-color inline styles.
  $body = fix_inline_color_style($body, $stats);
  //   4. Strip fixed font-size inline styles.
  $body = fix_inline_font_size($body, $stats);
  //   5. Warn about new-tab links.
  $body = fix_new_window_no_warning($body, $stats);
  //   6. Add descriptive labels to "here" and similar link text.
  $body = fix_ambiguous_links($body, $stats);
  //   7. Fix table headers and captions (no-op on posts without bare tables).
  $body = fix_tables($body, $stats);
  //   8. Fix dev-domain hrefs and raw-URL visible link text.
  $body = fix_url_link_text($body, $stats);

  if ($body === $original) {
    if ($verbose) {
      echo sprintf('  [%d] %s — unchanged', $nid, $node->getTitle()) . "\n";
    }
    continue;
  }

  $stats['nodes_changed']++;
  echo sprintf('  [%d] %s — CHANGED', $nid, $node->getTitle()) . "\n";

  if (!$dry_run) {
    $node->body->value = $body;
    $node->setNewRevision(FALSE);
    $node->save();
  }
}

// ── Report ────────────────────────────────────────────────────────────────────

echo "\n";
echo "══════════════════════════════════════════════════════\n";
echo ($dry_run ? '[DRY RUN] ' : '') . "Results\n";
echo "══════════════════════════════════════════════════════\n";
echo sprintf("  Nodes checked:              %d\n", $stats['nodes_checked']);
echo sprintf("  Nodes changed:              %d\n", $stats['nodes_changed']);
echo "  ── fixes by type ──\n";
echo sprintf("  inline_color_style:         %d\n", $stats['inline_color_style']);
echo sprintf("  inline_font_size:           %d\n", $stats['inline_font_size']);
echo sprintf("  new_window_no_warning:      %d\n", $stats['new_window_no_warning']);
echo sprintf("  styled_spans_stripped:      %d\n", $stats['styled_spans_stripped']);
echo sprintf("  headings_normalized:        %d\n", $stats['headings_normalized']);
echo sprintf("  tables_fixed:               %d\n", $stats['tables_fixed']);
echo sprintf("  url_link_text:              %d\n", $stats['url_link_text']);
echo sprintf("  ambiguous_link:             %d\n", $stats['ambiguous_link']);
echo "\n";

if ($dry_run) {
  echo "Run without --dry-run to apply changes.\n";
} else {
  echo "Next steps:\n";
  echo "  1. bin/static-site\n";
  echo "  2. source .venv/bin/activate && python3 scripts/a11y/generate_report.py blog/\n";
  echo "\n";
  echo "Note: missing_lang (3) and no_skip_nav (3) on the 3 legacy pages are\n";
  echo "template issues that will resolve after a successful Tome regeneration.\n";
}
