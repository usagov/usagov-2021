#!/usr/bin/env drush
<?php
/**
 * Script to update benefits_category field with category values before deleting the latter.
 */
# get all benefit nodes
$query = \Drupal::entityQuery('node')
  ->condition('type', 'basic_page')
  ->condition('field_category', '', '<>')
//  ->range(0, 20)
  ->accessCheck(TRUE);

$results = $query->execute();

$categoryTerms = getTermMap('category');
$benefitsTerms = getTermMap('benefits_category');
$transformMap = getTransformMap($categoryTerms, $benefitsTerms);

foreach ($results as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);

  $old_values = array_map(
    fn($item) => $item['target_id'],
    $node->get('field_category')->getValue()
  );
  $new_values = array_map(
    fn($item) => $transformMap[$item],
    $old_values
  );

  $node->set('field_benefits_category', $new_values);
  $node->save();

  echo sprintf(
    "| %6d | %-40s | %-20s | %20s |\n",
    $node->id(),
    substr($node->getTitle(), 0, 40),
    implode(', ', $old_values),
    implode(', ', $new_values),
  );
}

function getTermMap(string $vid) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid])
  ;
  $map = [];
  foreach ($terms as $term) {
    $map[$term->id()] = $term->name->value;
  }
  return $map;
}

function getTransformMap(array $src, array $dest): array {
  $map = [];
  $rev = array_flip($dest);
  foreach ($src as $srcTid => $oldTerm) {
    $map[$srcTid] = $rev[$oldTerm] ?? null;
  }

  return $map;
}
