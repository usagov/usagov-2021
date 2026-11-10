<?php

/**
 * @file
 * Generate the nginx map that redirects Spanish (/es) pages to English.
 *
 * USAGOV-2946: Redirect all Spanish-language pages to their English-language
 * equivalents, or to the English home page when no equivalent exists.
 *
 * Input is the published-pages.csv produced by `drush usapubcsv` at the end
 * of every Tome run (published to /ppr/published-pages.csv on the static
 * site). No Drupal bootstrap is needed.
 *
 * Usage:
 *   php scripts/es-redirect-map.php <published-pages.csv> \
 *     [--state-abbr=<nid<TAB>abbr file>] \
 *     [--out=.docker/src-www/etc/nginx/partials/es-redirect-map.conf] \
 *     [--report=<report.csv>]
 *
 * The optional --state-abbr file pairs state_directory_record pages (which
 * have no language toggle) by state abbreviation. Generate it on cms with:
 *   drush sqlq "SELECT entity_id, field_state_abbr_value FROM
 *     node__field_state_abbr WHERE bundle = 'state_directory_record'"
 *
 * Resolution order for each Spanish row:
 *   1. toggle         - Toggle URL points at a published English page.
 *   2. state-fallback - English state page with the same state abbreviation.
 *   3. wizard-root    - Wizard step: nearest ancestor path with a target.
 *   4. home           - Everything else ("/"), including unpublished targets.
 */

const COL_PAGE_TYPE = 1;
const COL_PAGE_ID = 3;
const COL_FULL_URL = 5;
const COL_TOGGLE_URL = 18;
const COL_LANGUAGE = 20;
const LANG_EN = 'USAGov English';
const LANG_ES = 'USAGov en Español';

$args = array_slice($argv, 1);
$csvPath = NULL;
$opts = [
  'out' => __DIR__ . '/../.docker/src-www/etc/nginx/partials/es-redirect-map.conf',
  'report' => NULL,
  'state-abbr' => NULL,
];
foreach ($args as $arg) {
  if (preg_match('/^--(out|report|state-abbr)=(.+)$/', $arg, $m)) {
    $opts[$m[1]] = $m[2];
  }
  elseif ($csvPath === NULL) {
    $csvPath = $arg;
  }
  else {
    fwrite(STDERR, "Unexpected argument: $arg\n");
    exit(1);
  }
}
if ($csvPath === NULL || !is_readable($csvPath)) {
  fwrite(STDERR, "Usage: php " . basename(__FILE__) . " <published-pages.csv> [--state-abbr=file] [--out=file] [--report=file]\n");
  exit(1);
}

/**
 * Normalize a URL or path to a lowercase path with no trailing slash ("/" for home).
 */
function es_norm_path(string $url): string {
  $path = parse_url(trim($url), PHP_URL_PATH) ?: '/';
  $path = strtolower(rawurldecode($path));
  $path = rtrim($path, '/');
  return $path === '' ? '/' : $path;
}

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh, escape: '');
if (($header[COL_TOGGLE_URL] ?? '') !== 'Toggle URL' || ($header[COL_LANGUAGE] ?? '') !== 'Page Language') {
  fwrite(STDERR, "Unexpected CSV header; column layout has changed.\n");
  exit(1);
}

$enPaths = [];
$enByNid = [];
$esRows = [];
while (($row = fgetcsv($fh, escape: '')) !== FALSE) {
  $lang = $row[COL_LANGUAGE] ?? '';
  if ($lang === LANG_EN) {
    $path = es_norm_path($row[COL_FULL_URL]);
    $enPaths[$path] = TRUE;
    $enByNid[$row[COL_PAGE_ID]] = $path;
  }
  elseif ($lang === LANG_ES) {
    $esRows[] = $row;
  }
}
fclose($fh);

// State abbreviation pairing.
$enStateByAbbr = [];
$esStateAbbrByNid = [];
if ($opts['state-abbr']) {
  foreach (file($opts['state-abbr'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$nid, $abbr] = array_map('trim', explode("\t", $line) + [1 => '']);
    if (!ctype_digit($nid) || $abbr === '') {
      continue;
    }
    if (isset($enByNid[$nid])) {
      $enStateByAbbr[$abbr] = $enByNid[$nid];
    }
    else {
      $esStateAbbrByNid[$nid] = $abbr;
    }
  }
}

$map = [];
$report = [];
$wizardPending = [];
foreach ($esRows as $row) {
  $esPath = es_norm_path($row[COL_FULL_URL]);
  if (preg_match('/[^\x21-\x7e]/', $esPath)) {
    fwrite(STDERR, "WARNING: non-ASCII path skipped (catch-all will send it home): $esPath\n");
    continue;
  }
  $type = $row[COL_PAGE_TYPE];
  $toggle = trim($row[COL_TOGGLE_URL]);
  $target = NULL;
  $reason = NULL;

  if ($toggle !== '' && $toggle !== 'None') {
    $togglePath = es_norm_path($toggle);
    if (isset($enPaths[$togglePath])) {
      [$target, $reason] = [$togglePath, 'toggle'];
    }
    else {
      [$target, $reason] = ['/', "home (unpublished target $togglePath)"];
    }
  }
  elseif ($type === 'state_directory_record' && isset($esStateAbbrByNid[$row[COL_PAGE_ID]], $enStateByAbbr[$esStateAbbrByNid[$row[COL_PAGE_ID]]])) {
    [$target, $reason] = [$enStateByAbbr[$esStateAbbrByNid[$row[COL_PAGE_ID]]], 'state-fallback'];
  }
  elseif (str_starts_with($type, 'wizard-')) {
    $wizardPending[] = [$esPath, $row];
    continue;
  }
  else {
    [$target, $reason] = ['/', 'home (no toggle)'];
  }
  $map[$esPath] = $target;
  $report[] = [$esPath, $target, $reason, $type, $row[COL_PAGE_ID]];
}

// Wizard steps: use the nearest ancestor path that resolved to a real page.
foreach ($wizardPending as [$esPath, $row]) {
  $target = '/';
  $reason = 'home (no toggle)';
  $parent = $esPath;
  while (($pos = strrpos($parent, '/')) > 0) {
    $parent = substr($parent, 0, $pos);
    if (isset($map[$parent]) && $map[$parent] !== '/') {
      [$target, $reason] = [$map[$parent], "wizard-root ($parent)"];
      break;
    }
  }
  $map[$esPath] = $target;
  $report[] = [$esPath, $target, $reason, $row[COL_PAGE_TYPE], $row[COL_PAGE_ID]];
}

ksort($map);

$lines = [];
$lines[] = '# GENERATED FILE - do not edit by hand.';
$lines[] = '# USAGOV-2946: redirect Spanish (/es) pages to their English equivalents.';
$lines[] = '# Regenerate with: php scripts/es-redirect-map.php <published-pages.csv> --state-abbr=<file>';
$lines[] = '# Source: ' . basename($csvPath) . ' (' . count($esRows) . ' Spanish rows), generated ' . gmdate('Y-m-d\TH:i:s\Z');
$lines[] = '# Anything under /es not listed here is sent to / by the catch-all in www_common.conf.';
$lines[] = '# map_hash_bucket_size / map_hash_max_size are set in nginx.conf (they must precede the first map).';
$lines[] = '';
$lines[] = 'map $request_uri_lowercase $es_redirect_target {';
$lines[] = '  default "";';
foreach ($map as $from => $to) {
  // Match with and without the trailing slash.
  $lines[] = sprintf('  %s %s;', $from, $to);
  $lines[] = sprintf('  %s/ %s;', $from, $to);
}
$lines[] = '}';
file_put_contents($opts['out'], implode("\n", $lines) . "\n");

if ($opts['report']) {
  $rf = fopen($opts['report'], 'w');
  fputcsv($rf, ['es_path', 'target', 'reason', 'page_type', 'page_id'], escape: '');
  usort($report, fn($a, $b) => strcmp($a[0], $b[0]));
  foreach ($report as $r) {
    fputcsv($rf, $r, escape: '');
  }
  fclose($rf);
}

$counts = [];
foreach ($report as $r) {
  $key = preg_replace('/ \(.*\)$/', '', $r[2]);
  $counts[$key] = ($counts[$key] ?? 0) + 1;
}
ksort($counts);
fwrite(STDERR, sprintf("Wrote %d Spanish paths to %s\n", count($map), $opts['out']));
foreach ($counts as $k => $n) {
  fwrite(STDERR, sprintf("  %-15s %d\n", $k, $n));
}
