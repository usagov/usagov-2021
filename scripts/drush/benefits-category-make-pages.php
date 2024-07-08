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
  \Drush\Drush::output()->writeln("<info>English page already exists with same title.</info>");
  $nid = array_shift($englishNid);
  $englishPage = Node::load($nid);
} else {
  // Create English Page
  $englishPage = createBasicPage(
    title: 'Find benefits by category',
    langcode: 'en',
    intro: 'Use the benefit finder tool or search by category to learn which benefits you and your family may be eligible for and how to apply.',
    pageType: $benefitsSearchType,
    lifeEventsTitle: 'Benefit finder tool',
    lifeEventsDescr: 'Answer some basic questions to get a customized list of potential government benefits you may be eligible for during life events.',
    searchTitle: 'Find benefits by category',
    searchDescr: 'Select one or more categories below to find benefits you may qualify for and information on how to apply.'
  );
}

$spanishNid = \Drupal::entityQuery('node')
  ->condition('title', '¿A cuáles beneficios califica usted?')
  ->accessCheck(FALSE)
  ->execute();

if ($spanishNid) {
  \Drush\Drush::output()->writeln("<info>Spanish page already exists with same title.</info>");
  $nid = array_shift($spanishNid);
  $spanishPage = Node::load($nid);
} else {
  // Create Spanish Page
  $spanishPage = createBasicPage(
    title: '¿A cuáles beneficios califica usted?',
    langcode: 'es',
    intro: '',
    pageType: $benefitsSearchType,
    lifeEventsTitle: 'Encuentre beneficios en estas etapas de su vida',
    lifeEventsDescr: "Responda unas preguntas básicas para obtener una lista personalizada de los beneficios del Gobierno a los que podría ser elegibles. También conocerá cómo aplicar a cada beneficio.\n\nNo compartimos ni guardamos la información que usted ingrese en el buscador de beneficios.",
    searchTitle: 'Busque beneficios por categoría',
    searchDescr: 'Seleccione una o más de las categorías a continuación para encontrar los beneficios a los que podría calificar y conocer cómo aplicar.'
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

function createBasicPage(
  string $title,
  string $langcode,
  string $intro,
  string $pageType,
  string $lifeEventsTitle,
  string $lifeEventsDescr,
  string $searchTitle,
  string $searchDescr,
) {
  $node = Node::create([
    'type' => 'basic_page',
    'title' => $title,
    'langcode' => $langcode,
    'field_page_intro' => $intro,
    'field_page_type' => $pageType,
    'field_benefits_life_events_title' => $lifeEventsTitle,
    'field_benefits_life_events_descr' => $lifeEventsDescr,
    'field_benefits_search_title' => $searchTitle,
    'field_benefits_search_descr' => $searchDescr,
  ]);
  $node->setPublished(true);
  $node->set('moderation_state', 'published');
  $node->save();

  \Drush\Drush::output()->writeln("<info>Creating ${title} Page</info>");
  return $node;
}
