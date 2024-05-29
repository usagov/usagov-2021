#!/usr/bin/env drush
<?php

// Exports nid, title, benefits category fields of tagged nodes to JSON so that
// we can automatically tag content after updating the database with production DB copy.
$file = $_SERVER['argv'][4] ?? null;
if (!$file) {
  trigger_error("First argument for import file is missing", E_USER_ERROR);
}

if (!$file = realpath($file)) {
  trigger_error("File does not exist.", E_USER_ERROR);
}

$benefitsTerms = getTermMap('benefits_category');

$handle = new SplFileObject($file);
$handle->setFlags(SplFileObject::SKIP_EMPTY);
while (!$handle->eof()) {
  $line = trim($handle->getCurrentLine());
  try {
    $data = json_decode($line, false, 512, JSON_THROW_ON_ERROR);

    $termIds = array_map(fn($term) => array_search($term, $benefitsTerms), $data->terms);
    $termIds = array_filter($termIds);

    $node = \Drupal\node\Entity\Node::load($data->nid);
    $node->set('field_benefits_category', $termIds);
    $node->save();
  } catch (JsonException $e) {
    var_dump($line);
    trigger_error($e->getMessage(), E_USER_WARNING);
  }
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
