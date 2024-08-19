#!/usr/bin/env drush
<?php

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drush\Drush;

// Create English and Spanish landing pages.
// Useful after refreshing the database.
$pageTypes = getTermMap('page_type');
$benefitsSearchType = array_search('Benefits Category Search', $pageTypes);

$englishNid = \Drupal::entityQuery('node')
  ->condition('title', 'What government benefits do you qualify for?')
  ->accessCheck(FALSE)
  ->execute();

if ($englishNid) {
  Drush::output()->writeln("<info>English page already exists with same title.</info>");
  $nid = array_shift($englishNid);
  $englishPage = Node::load($nid);
  if (!$englishPage->isPublished()) {
    publishPage($englishPage);
  }
}
else {
  // Create English Page.
  $englishPage = createBasicPage(
    title: 'Find benefits by category',
    langcode: 'en',
    body: '<p>
    Use the <a href="#benefit-finder">benefit finder tool</a> or <a href="#search">search by category</a> to learn which benefits you and your family may be eligible for and how to apply.
</p>',
    pageType: $benefitsSearchType,
    lifeEventsTitle: 'Benefit finder tool',
    lifeEventsDescr: 'Answer some basic questions to get a customized list of potential government benefits you may be eligible for during life events.',
    lifeEventsID: 'benefit-finder',
    searchTitle: 'Find benefits by category',
    searchDescr: 'Select one or more categories below to find benefits you may qualify for and information on how to apply.',
    searchID: 'search'
  );
}

$spanishNid = \Drupal::entityQuery('node')
  ->condition('title', '¿A cuáles beneficios califica usted?')
  ->accessCheck(FALSE)
  ->execute();

if ($spanishNid) {
  Drush::output()->writeln("<info>Spanish page already exists with same title.</info>");
  $nid = array_shift($spanishNid);
  $spanishPage = Node::load($nid);
  if (!$spanishPage->isPublished()) {
    publishPage($spanishPage);
  }
}
else {
  // Create Spanish Page.
  $spanishPage = createBasicPage(
    title: '¿A cuáles beneficios califica usted?',
    langcode: 'es',
    body: '',
    pageType: $benefitsSearchType,
    lifeEventsTitle: 'Encuentre beneficios en estas etapas de su vida',
    lifeEventsDescr: "Responda unas preguntas básicas para obtener una lista personalizada de los beneficios del Gobierno a los que podría ser elegibles. También conocerá cómo aplicar a cada beneficio.\n\nNo compartimos ni guardamos la información que usted ingrese en el buscador de beneficios.",
    lifeEventsID: 'buscado-beneficios',
    searchTitle: 'Busque beneficios por categoría',
    searchDescr: 'Seleccione una o más de las categorías a continuación para encontrar los beneficios a los que podría calificar y conocer cómo aplicar.',
    searchID: 'buscar',
  );
}


Drush::output()->writeln("<info>Linking English page to Spanish page</info>");
$englishPage->set('field_language_toggle', $spanishPage->id());
$englishPage->save();

Drush::output()->writeln("<info>Linking Spanish page to English page</info>");
$spanishPage->set('field_language_toggle', $englishPage->id());
$spanishPage->save();

Drush::output()->writeln("<info>Adding callout to /benefits page</info>");
$benefitsPage = getNodeForPath('/benefits');
$benefitsPage->set('field_benefits_callout_descr', 'Do a customized search for benefits you may qualify for.');
$benefitsPage->set('field_benefits_callout_button', 'Begin benefits search');
$benefitsPage->set('field_benefits_callout_ref', $englishPage->id());
$benefitsPage->save();

Drush::output()->writeln("<info>Adding callout to /es/beneficios-gobierno page</info>");
$benefitsSpanishPage = getNodeForPath('/beneficios-gobierno', 'es');
$benefitsSpanishPage->set('field_benefits_callout_descr', 'Haga una búsqueda personalizada de los beneficios a los que podría calificar.');
$benefitsSpanishPage->set('field_benefits_callout_button', 'Encuentre beneficios');
$benefitsSpanishPage->set('field_benefits_callout_ref', $englishPage->id());
$benefitsSpanishPage->save();

Drush::output()->writeln("<info>Adding callout to English version of home page</info>");
$homePage = Node::load(1);
$homePage->set('field_homepage_benefits_title', 'Government benefits and financial assistance');
$homePage->set('field_homepage_benefits_descr', 'Discover government benefits that you may be eligible for and learn how to apply');
$homePage->set('field_homepage_benefits_button', 'Begin benefits search');
$homePage->set('field_homepage_benefits_ref', $englishPage->id());
$homePage->save();

Drush::output()->writeln("<info>Adding callout to Spanish version of home page</info>");
$translation = $homePage->getTranslation('es');
$translation->set('field_homepage_benefits_title', 'Ayuda económica y beneficios del Gobierno');
$translation->set('field_homepage_benefits_descr', 'Encuentre información sobre los programas de ayuda del Gobierno de EE. UU., requisitos para solicitar beneficios, cómo y dónde aplicar.');
$translation->set('field_homepage_benefits_button', 'Encuentre beneficios');
$translation->set('field_homepage_benefits_ref', $spanishPage->id());
$translation->save();

function getTermMap(string $vid) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid]);
  $map = [];
  foreach ($terms as $term) {
    $map[$term->id()] = $term->name->value;
  }
  return $map;
}

function createBasicPage(
  string $title,
  string $langcode,
  string $body,
  string $pageType,
  string $lifeEventsTitle,
  string $lifeEventsDescr,
  string $lifeEventsID,
  string $searchTitle,
  string $searchDescr,
  string $searchID,
) {
  $node = Node::create([
    'type' => 'basic_page',
    'title' => $title,
    'langcode' => $langcode,
    'body' => ['value' => $body, 'format' => 'uswds'],
    'field_page_type' => $pageType,
    'field_benefits_life_events_title' => $lifeEventsTitle,
    'field_benefits_life_events_descr' => $lifeEventsDescr,
    'field_benefits_life_events_id' => $lifeEventsID,
    'field_benefits_search_title' => $searchTitle,
    'field_benefits_search_descr' => $searchDescr,
    'field_benefits_search_id' => $searchID,
  ]);
  $node->setPublished(TRUE);
  $node->set('moderation_state', 'published');
  $node->save();

  Drush::output()->writeln("<info>Creating ${title} Page</info>");
  return $node;
}

function getNodeForPath(string $path, ?string $langcode = null): Node {
  $alias  = \Drupal::service('path_alias.manager')->getPathByAlias($path, $langcode);
  $params = Url::fromUri("internal:" . $alias)->getRouteParameters();

  if ($params['node']) {
    return Node::load($params['node']);
  }

  throw new RuntimeException("Node for $path not found.");
}

function publishPage(Node $node): void {
  if (!$node->isPublished()) {
    $node->setPublished(TRUE);
    $node->set('moderation_state', 'published');
    $node->save();

    Drush::output()->writeln("<info>Publishing {$node->getTitle()} Page</info>");
  }
}
