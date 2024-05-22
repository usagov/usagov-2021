<?php
/**
 * Create terms for benefits category vocabulary
 */
function usagov_category_search_post_update_terms() {
  $vocab = \Drupal\taxonomy\Entity\Vocabulary::load('benefits_category');
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

  foreach ($langs as $lang => $list) {
    foreach ($list as $category) {
      $term = \Drupal\taxonomy\Entity\Term::create([
        'vid' => $vocab->id(),
        'name' => $category,
        'langcode' => $lang,
      ]);
      $term->save();
    }
  }
}
