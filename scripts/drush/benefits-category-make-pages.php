#!/usr/bin/env drush
<?php

use Drupal\node\Entity\Node;

// Create English and Spanish landing pages.
// Useful after refreshing the database.
$pageTypes = getTermMap('page_type');
$benefitsSearchType = array_search('Benefits Category Search', $pageTypes);

$englishNid = \Drupal::entityQuery('node')
  ->condition('title', 'Find benefits by category')
  ->accessCheck(FALSE)
  ->execute();

if ($englishNid) {
  $nid = array_shift($englishNid);
  $englishPage = Node::load($nid);
} else {
  // Create English Page
  $englishPage = createBasicPage(
    title: 'Find benefits by category',
    langcode: 'en',
    intro: 'Use the benefit finder tool or search by category to learn which benefits you and your family may be eligible for and how to apply.',
    pageType: $benefitsSearchType,
    alias: '/benefits-search'
  );
}

$spanishNid = \Drupal::entityQuery('node')
  ->condition('title', '¿A cuáles beneficios califica usted?')
  ->accessCheck(FALSE)
  ->execute();

if ($spanishNid) {
  $nid = array_shift($spanishNid);
  $spanishPage = Node::load($nid);
} else {
  // Create Spanish Page
  $spanishPage = createBasicPage(
    title: '¿A cuáles beneficios califica usted?',
    langcode: 'es',
    intro: '',
    pageType: $benefitsSearchType,
    alias: '/es/buscador-beneficios',
  );
}

// relate English to Spanish
$englishPage->set('field_language_toggle', $spanishPage->id());
$englishPage->save();
// relate Spanish to English t
$spanishPage->set('field_language_toggle', $englishPage->id());
$spanishPage->save();

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

function createBasicPage(string $title, string $langcode, string $intro, string $pageType, string $alias) {
  $node = Node::create([
    'type' => 'basic_page',
    'title' => $title,
    'langcode' => $langcode,
    'field_page_intro' => $intro,
    'field_page_type' => $pageType,
  ]);
  $node->save();

  $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
    'path' => "/node/" . $node->id(),
    'alias' => $alias,
    'langcode' => "en",
  ]);
  $path_alias->save();
  \Drupal::entityTypeManager()->getStorage('path_alias')->save($path_alias);

  return $node;
}
