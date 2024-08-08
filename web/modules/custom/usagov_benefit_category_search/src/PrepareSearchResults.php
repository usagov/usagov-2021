<?php

namespace Drupal\usagov_benefit_category_search;

use Drupal\taxonomy\Entity\Term;
use Drupal\views\ViewExecutable;

/**
 * Cleans up search results JSON entries.
 */
class PrepareSearchResults {

  /**
   * Prepares view for display.
   *
   * Guards against showing a category term that is from a
   * different language than requested via the views context/arguments.
   */
  public function cleanTerms(ViewExecutable $view): ViewExecutable {
    //
    // For filtering, remove categories that are not in the language we want.
    foreach ($view->result as $row) {
      $categories = $row->_entity->field_benefits_category;

      foreach ($categories as $category) {
        $termID = $category->get('target_id')->getValue();
        if (($term = Term::load($termID))
             && $term->language()->getId() !== $view->argument["langcode"]->argument) {
          // Removing an item re-keys the internal array, but we can use
          // the name property for a list item, which tracks the index
          // after re-keying.
          $categories->removeItem($category->getName());
        }
      }
    }

    // Next, remove terms from other languages for the categories we show in
    // the search results.
    foreach ($view->field['term_node_tid']->items as $src => $terms) {
      foreach ($terms as $termID => $values) {
        if (($term = Term::load($termID))
            && $term->language()->getId() !== $view->argument["langcode"]->argument) {
          unset($view->field['term_node_tid']->items[$src][$termID]);
        }
      }
    }

    return $view;
  }

}
