<?php

/**
 * @file
 * Generates the www nginx map that redirects Spanish pages to English.
 *
 * USAGOV-2946: Spanish pages are hidden from the public site. The www app
 * answers every /es request with a 302: to the English equivalent when this
 * map has one, otherwise to the English homepage. Spanish content stays
 * published in the CMS.
 *
 * Usage, from the repo root, against a current prod snapshot (bin/db-update):
 *   bin/drush scr /var/www/scripts/drush/spanish-redirect-map.php
 *
 * Writes .docker/src-www/etc/nginx/partials/spanish_redirect_map_entries.conf,
 * which is committed and deployed with the www image. Rerun it when Spanish or
 * English pages are added or re-paired; until then, new Spanish pages go to
 * the English homepage.
 *
 * Pairs come from:
 *  - field_language_toggle on Spanish nodes and taxonomy terms;
 *  - field_state_abbr for state_directory_record nodes, which have no toggle;
 *  - Spanish redirect entities whose destination is a paired Spanish page.
 * A pair is written only when both pages are published and the English page
 * has a path alias.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\redirect\Entity\Redirect;
use Drush\Drush;

$output_file = dirname(DRUPAL_ROOT) . '/.docker/src-www/etc/nginx/partials/spanish_redirect_map_entries.conf';

$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');
$term_storage = $entity_type_manager->getStorage('taxonomy_term');
$database = \Drupal::database();
$front_path = \Drupal::config('system.site')->get('page.front');

/**
 * Spanish map key (lower case, no trailing slash) => English path.
 *
 * @var array<string, string> $map
 */
$map = [];
$stats = [
  'node pairs' => 0,
  'state pairs' => 0,
  'term pairs' => 0,
  'redirect pairs' => 0,
];
/** @var array<string, int> $unpaired */
$unpaired = [];
/**
 * Entity type => Spanish entity ID => English path, for resolving redirects.
 *
 * @var array<string, array<int|string, string>> $paired
 */
$paired = ['node' => [], 'taxonomy_term' => []];

/**
 * Builds the key nginx compares against.
 *
 * Matches the www app, which lower-cases ASCII only (Lua string.lower) and
 * strips trailing slashes before the lookup.
 */
function usagov_es_map_key(string $path): string {
  return rtrim(strtolower($path), '/');
}

/**
 * Returns TRUE when a path is safe to write as a quoted nginx map string.
 */
function usagov_es_map_safe(string $path): bool {
  return $path !== '' && $path[0] === '/' && !preg_match('/["\\\\$;{}\s]|[\x00-\x1f\x7f]/', $path);
}

/**
 * Returns the public English path for an entity, or NULL if it has none.
 */
function usagov_es_map_english_path(?ContentEntityInterface $entity, string $front_path): ?string {
  if (!$entity || !$entity->hasTranslation('en')) {
    return NULL;
  }
  $entity = $entity->getTranslation('en');
  if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
    return NULL;
  }
  $internal = '/' . $entity->toUrl('canonical')->getInternalPath();
  if ($internal === $front_path) {
    return '/';
  }
  $path = $entity->toUrl('canonical', ['language' => $entity->language()])->toString();
  // Unaliased /node/N and /taxonomy/term/N paths are not exported by Tome.
  if ($path === $internal || !usagov_es_map_safe($path)) {
    return NULL;
  }
  return $path;
}

/**
 * Returns every published Spanish alias of an internal path, with /es prefix.
 *
 * @return string[]
 *   Spanish paths.
 */
function usagov_es_map_spanish_paths(string $internal_path): array {
  $aliases = \Drupal::database()->select('path_alias', 'a')
    ->fields('a', ['alias'])
    ->condition('path', $internal_path)
    ->condition('langcode', 'es')
    ->condition('status', 1)
    ->execute()
    ->fetchCol();
  return array_map(fn ($alias) => '/es' . $alias, $aliases);
}

/**
 * Adds a pair to the map. Returns TRUE if at least one key was added.
 *
 * @param array<string, string> $map
 *   The map being built.
 * @param string[] $spanish_paths
 *   Spanish paths to send to $english_path.
 */
function usagov_es_map_add(array &$map, array $spanish_paths, string $english_path): bool {
  $added = FALSE;
  foreach ($spanish_paths as $spanish_path) {
    $key = usagov_es_map_key($spanish_path);
    if (!str_starts_with($key, '/es/') || !usagov_es_map_safe($key) || isset($map[$key])) {
      continue;
    }
    $map[$key] = $english_path;
    $added = TRUE;
  }
  return $added;
}

// English state pages, keyed by state abbreviation.
$english_states = [];
$query = $database->select('node_field_data', 'n');
$query->join('node__field_state_abbr', 's', 's.entity_id = n.nid AND s.langcode = n.langcode');
$query->fields('n', ['nid'])
  ->fields('s', ['field_state_abbr_value'])
  ->condition('n.type', 'state_directory_record')
  ->condition('n.langcode', 'en')
  ->condition('n.status', 1);
foreach ($query->execute() as $row) {
  $english_states[strtoupper(trim($row->field_state_abbr_value))] = (int) $row->nid;
}

// Spanish nodes. Node 1 is the homepage; the /es catch-all sends it to "/".
$spanish_nids = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('langcode', 'es')
  ->condition('default_langcode', 1)
  ->condition('status', 1)
  ->condition('nid', 1, '<>')
  ->execute();
foreach (array_chunk($spanish_nids, 100) as $chunk) {
  /** @var \Drupal\node\NodeInterface $node */
  foreach ($node_storage->loadMultiple($chunk) as $node) {
    $spanish_paths = usagov_es_map_spanish_paths('/node/' . $node->id());
    if (!$spanish_paths) {
      continue;
    }
    $english_path = NULL;
    $source = 'node pairs';
    if ($node->hasField('field_language_toggle') && !$node->get('field_language_toggle')->isEmpty()) {
      $english_path = usagov_es_map_english_path($node->get('field_language_toggle')->entity, $front_path);
    }
    if (!$english_path && $node->bundle() === 'state_directory_record' && $node->hasField('field_state_abbr')) {
      $abbr = strtoupper(trim((string) $node->get('field_state_abbr')->value));
      if (isset($english_states[$abbr])) {
        $english_path = usagov_es_map_english_path($node_storage->load($english_states[$abbr]), $front_path);
        $source = 'state pairs';
      }
    }
    if ($english_path) {
      $paired['node'][$node->id()] = $english_path;
      if (usagov_es_map_add($map, $spanish_paths, $english_path)) {
        $stats[$source]++;
      }
    }
    else {
      $unpaired['node:' . $node->bundle()] = ($unpaired['node:' . $node->bundle()] ?? 0) + 1;
    }
  }
  $node_storage->resetCache($chunk);
}

// Spanish taxonomy terms (for example, the scam wizard).
$spanish_tids = $term_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('langcode', 'es')
  ->condition('default_langcode', 1)
  ->condition('status', 1)
  ->execute();
/** @var \Drupal\taxonomy\TermInterface $term */
foreach ($term_storage->loadMultiple($spanish_tids) as $term) {
  $spanish_paths = usagov_es_map_spanish_paths('/taxonomy/term/' . $term->id());
  if (!$spanish_paths) {
    continue;
  }
  $english_path = NULL;
  if ($term->hasField('field_language_toggle') && !$term->get('field_language_toggle')->isEmpty()) {
    $english_path = usagov_es_map_english_path($term->get('field_language_toggle')->entity, $front_path);
  }
  if ($english_path) {
    $paired['taxonomy_term'][$term->id()] = $english_path;
    if (usagov_es_map_add($map, $spanish_paths, $english_path)) {
      $stats['term pairs']++;
    }
  }
  else {
    $unpaired['term:' . $term->bundle()] = ($unpaired['term:' . $term->bundle()] ?? 0) + 1;
  }
}

// Spanish redirect entities that land on a paired Spanish page.
if (\Drupal::moduleHandler()->moduleExists('redirect')) {
  foreach (Redirect::loadMultiple() as $redirect) {
    $source = $redirect->getSource();
    if (!empty($source['query']) || empty($source['path'])) {
      continue;
    }
    $source_path = '/' . ltrim($source['path'], '/');
    if ($redirect->language()->getId() === 'es' && !str_starts_with($source_path . '/', '/es/')) {
      $source_path = '/es' . $source_path;
    }
    if (!str_starts_with($source_path, '/es/')) {
      continue;
    }
    $english_path = NULL;
    try {
      $destination = $redirect->getRedirectUrl();
      if ($destination->isExternal()) {
        continue;
      }
      // Routed destinations would render without the /es alias here (the CLI
      // runs in English), so resolve them by entity ID instead.
      if ($destination->isRouted() && preg_match('/^entity\.(node|taxonomy_term)\.canonical$/', $destination->getRouteName(), $matches)) {
        $id = $destination->getRouteParameters()[$matches[1]] ?? NULL;
        $english_path = $paired[$matches[1]][$id] ?? NULL;
      }
      else {
        $english_path = $map[usagov_es_map_key((string) parse_url($destination->toString(), PHP_URL_PATH))] ?? NULL;
      }
    }
    catch (\Exception $e) {
      continue;
    }
    if ($english_path && usagov_es_map_add($map, [$source_path], $english_path)) {
      $stats['redirect pairs']++;
    }
  }
}

ksort($map);

$latest_change = $database->query('SELECT MAX(changed) FROM {node_field_data}')->fetchField();
$lines = [
  '# GENERATED by scripts/drush/spanish-redirect-map.php. Do not edit by hand.',
  '# USAGOV-2946: exact Spanish -> English page pairs, included by spanish_redirect_maps.conf.',
  '# Generated ' . gmdate('Y-m-d\TH:i:s\Z') . ' from a database whose latest node change is ' . gmdate('Y-m-d\TH:i:s\Z', (int) $latest_change) . '.',
  '# Entries: ' . count($map),
  '',
];
foreach ($map as $key => $english_path) {
  $lines[] = '"' . $key . '" "' . $english_path . '";';
}
if (file_put_contents($output_file, implode("\n", $lines) . "\n") === FALSE) {
  throw new \RuntimeException("Could not write $output_file");
}

$output = Drush::output();
$output->writeln('Wrote ' . count($map) . ' entries to ' . $output_file);
foreach ($stats as $label => $count) {
  $output->writeln("  $label: $count");
}
ksort($unpaired);
$output->writeln('Published Spanish pages with no English pair (sent to the homepage):');
foreach ($unpaired as $label => $count) {
  $output->writeln("  $label: $count");
}
