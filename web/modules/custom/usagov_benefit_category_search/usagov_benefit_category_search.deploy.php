<?php

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Add "Benefits Search" page type term for basic page nodes
 *
 * @return void
 */
function usagov_benefit_category_search_deploy_add_page_type(): void {
  $vocab = Vocabulary::load('page_type');
  if (!$vocab) {
    return;
  }

  $term = Term::create([
    'vid' => $vocab->id(),
    'name' => 'Benefits Category Search',
    'weight' => 5,
  ]);

  $term->save();
}

/**
 * Create terms for benefits category vocabulary
 *
 * @return void
 */
function usagov_benefit_category_search_deploy_terms(): void {
  $vocab = Vocabulary::load('benefits_category');
  if (!$vocab) {
    return;
  }

  $langs = [
    'en' => [
      'Cash Assistance', 'Death Benefits', 'Disability', 'Disaster and emergency',
      'Education', 'Food', 'Health care', 'Housing', 'Jobs',
      'Military and veterans', 'Retirement and seniors', 'Utilities'
    ],
    'es' => [
      'Alimentación', 'Asistencia financiera', 'Desastres y emergencias',
      'Discapacidad', 'Educación', 'Fallecimiento', 'Jubilación y adultos mayores',
      'Militar y veteranos', 'Salud', 'Servicios básicos', 'Trabajo', 'Vivienda'
    ],
  ];

  $weight = 0;
  foreach ($langs as $lang => $list) {
    foreach ($list as $category) {
      $term = Term::create([
        'vid' => $vocab->id(),
        'name' => $category,
        'langcode' => $lang,
        'weight' => $weight,
      ]);
      $term->save();
      $weight++;
    }
  }
}
