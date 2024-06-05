#!/usr/bin/env drush
<?php

// Exports nid, title, benefits category fields of tagged nodes to JSON so that
// we can automatically tag content after updating the database with production DB copy.

# get all benefit nodes
$query = \Drupal::entityQuery('node')
  ->condition('type', 'basic_page')
  ->condition('field_benefits_category', '', '<>')
  ->accessCheck(TRUE);

$results = $query->execute();

$benefitsTerms = getTermMap('benefits_category');

foreach ($results as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);

  $tids = array_map(
    fn($item) => $item['target_id'],
    $node->get('field_benefits_category')->getValue()
  );
  $terms = array_map(
    fn($item) => $benefitsTerms[$item['target_id']],
    $node->get('field_benefits_category')->getValue()
  );

  $data = new class($nid, $title = $node->getTitle(), $terms, $tids) {
    public function __construct(
      public string $nid,
      public string $title,
      public array $terms,
      public array $tids,
    ) {
    }
  };

  echo json_encode($data, false, JSON_THROW_ON_ERROR) . PHP_EOL;
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
