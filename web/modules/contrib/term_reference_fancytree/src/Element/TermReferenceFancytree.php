<?php

namespace Drupal\term_reference_fancytree\Element;

use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html;
use Drupal\taxonomy\Entity\Term;

/**
 * Term Reference Tree Form Element.
 *
 * @FormElement("term_reference_fancytree")
 */
class TermReferenceFancytree extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processTree'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processTree(&$element, FormStateInterface $form_state, &$complete_form) {

    if (!empty($element['#vocabulary'])) {

      // Get the ancestors of the selected items.
      // If we are processing input (submit) we want to pass the state with
      // selected items.
      if ($form_state->isProcessingInput() && $form_state->getUserInput()[$element['#field_name']]) {
        $ancestors = TermReferenceFancytree::getSelectedAncestors($form_state->getUserInput()[$element['#field_name']], TRUE);
      }
      // If we are not we want the default values (previously submitted).
      else {
        $ancestors = TermReferenceFancytree::getSelectedAncestors($element['#default_value'], FALSE);
      }

      // Build a list of top level nodes, including children if containing
      // selected items.
      $list = TermReferenceFancytree::getTopLevelNodes($element, $ancestors, $form_state);

      // Attach our libary and settings.
      $element['#attached']['library'][] = 'term_reference_fancytree/tree';
      $element['#attached']['drupalSettings']['term_reference_fancytree'][$element['#id']]['tree'][] = [
        'id' => $element['#id'],
        'name' => $element['#name'],
        'source' => $list,
        'select_all' => $element['#select_all'],
        'select_children' => $element['#select_children'],
      ];

      if ($element['#select_all']) {
        $element['#markup'] = '<a href="#" class="selectAll">Select all</a>';
      }

      // Create HTML wrappers.
      $element['tree'] = [];
      $element['tree']['#prefix'] = '<div id="' . $element['#id'] . '">';
      $element['tree']['#suffix'] = '</div>';
    }

    return $element;
  }

  /**
   * Function that goes through the default values and obtains their ancestors.
   *
   * @param array $values
   *   The selected items.
   * @param bool $processing_input
   *   Flag if it is processing the input or not.
   *
   * @return array
   *   The list of ancestors.
   */
  public static function getSelectedAncestors(array $values, $processing_input) {

    $all_ancestors = [];

    foreach ($values as $value) {
      if (isset($value['target_id'])) {
        // Check if we are processing input or not because the structure of
        // default values and form state differs.
        if (!$processing_input) {
          $value = $value['target_id'];
        }
      }

      $term_ancestors = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadAllParents($value);

      foreach ($term_ancestors as $ancestor) {
        $all_ancestors[$ancestor->id()] = $ancestor;
      }
    }

    return $all_ancestors;
  }

  /**
   * Function that returns the top level nodes for the tree.
   *
   * If multiple vocabularies, it will return the vocabulary names, otherwise
   * it will return the top level terms.
   *
   * @param array $element
   *   The form element.
   * @param array $ancestors
   *   The list of term ancestors for default values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The nested JSON array with the top level nodes.
   */
  public static function getTopLevelNodes(array $element, array $ancestors, FormStateInterface $form_state) {
    // If we have more than one vocabulary, we load the vocabulary names as
    // the initial level.
    if (count($element['#vocabulary']) > 1) {
      return TermReferenceFancytree::getVocabularyNamesJsonArray($element, $form_state, $ancestors);
    }
    // Otherwise, we load the list of terms on the first level.
    else {
      $taxonomy_vocabulary = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_vocabulary')
        ->load(reset($element['#vocabulary'])->id());
      // Load the terms in the first level.
      $terms = TermReferenceFancytree::loadTerms($taxonomy_vocabulary, 0);
      // Convert the terms list into the Fancytree JSON format.
      return TermReferenceFancytree::getNestedListJsonArray($terms, $element, $ancestors, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    $selected_terms = [];
    // Ensure our input is not empty and loop through the input values for
    // submission.
    // @Todo check if we need this.
    if (is_array($input) && !empty($input)) {
      foreach ($input as $tid) {
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->load($tid);
        if ($term) {
          $selected_terms[] = $tid;
        }
      }
    }
    return $selected_terms;
  }

  /**
   * Load one single level of terms, sorted by weight and alphabet.
   */
  public static function loadTerms($vocabulary, $parent = 0) {
    try {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary->id())
        ->condition('parent', $parent)
        ->sort('weight')
        ->sort('name');

      $tids = $query->execute();

      $terms = TermReferenceFancytree::getTermStorage()
        ->loadMultiple($tids);

      $language = \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getId();

      foreach ($terms as $tid => $term) {
        if ($term->hasTranslation($language)) {
          $terms[$tid] = $term->getTranslation($language);
        }
      }

      return $terms;
    }
    catch (QueryException $e) {
      // This site is still using the pre-Drupal 8.5 database schema, where
      // https://www.drupal.org/project/drupal/issues/2543726 was not yet
      // committed to Drupal core.
      // @todo Remove both the try/catch wrapper and the code below the catch-
      // statement once the module only supports Drupal 8.5 or
      // newer.
    }

    $database = \Drupal::database();
    $query = $database->select('taxonomy_term_data', 'td');
    $query->fields('td', ['tid']);
    $query->condition('td.vid', $vocabulary->id());
    $query->join('taxonomy_term_hierarchy', 'th', 'td.tid = th.tid AND th.parent = :parent', [':parent' => $parent]);
    $query->join('taxonomy_term_field_data', 'tfd', 'td.tid = tfd.tid');
    $query->orderBy('tfd.weight');
    $query->orderBy('tfd.name');

    $result = $query->execute();

    $tids = [];
    foreach ($result as $record) {
      $tids[] = $record->tid;
    }

    return \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadMultiple($tids);
  }

  /**
   * Helper function that transforms a flat taxonomy tree in a nested array.
   */
  public static function getNestedList($tree = [], $max_depth = NULL, $parent = 0, $parents_index = [], $depth = 0) {
    foreach ($tree as $term) {
      foreach ($term->parents as $term_parent) {
        if ($term_parent == $parent) {
          $return[$term->id()] = $term;
        }
        else {
          $parents_index[$term_parent][$term->id()] = $term;
        }
      }
    }

    foreach ($return as &$term) {
      if (isset($parents_index[$term->id()]) && (is_null($max_depth) || $depth < $max_depth)) {
        $term->children = TermReferenceFancytree::getNestedList($parents_index[$term->id()], $max_depth, $term->id(), $parents_index, $depth + 1);
      }
    }

    return $return;
  }

  /**
   * Function that generates the nested list for the JSON array structure.
   */
  public static function getNestedListJsonArray($terms, $element, $ancestors = NULL, $form_state = NULL) {
    $items = [];
    if (!empty($terms)) {
      foreach ($terms as $term) {
        $item = [
          'title' => Html::escape($term->getName()),
          'key' => $term->id(),
        ];

        // Checking the term against the form state and default values and
        // if present, mark as selected.
        if ($form_state && $form_state->getUserInput()) {
          if (in_array($term->id(), $form_state->getValues()[$element['#field_name']])) {
            $item['selected'] = TRUE;
          }
        }
        elseif (isset($element['#default_value']) && is_numeric(array_search($term->id(), array_column($element['#default_value'], 'target_id')))) {
          $item['selected'] = TRUE;
        }

        // If the term is an ancestor we will want to add it to the tree instead
        // of marking it as lazy load.
        if (isset($ancestors[$term->id()])) {
          // We add an active trail class to the item.
          $item['extraClasses'] = "activeTrail";
          // We load all the children and pass it to this function recursively.
          $children = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadChildren($term->id());
          $child_items = self::getNestedListJsonArray($children, $element, $ancestors, $form_state);
          // If we get some children, we add those under the item.
          if ($child_items) {
            $item['children'] = $child_items;
          }
        }
        // If the term is not in the ancestors we mark it for lazy loading if
        // it has children.
        elseif (isset($term->children) || TermReferenceFancytree::getChildCount($term->id()) >= 1) {
          // If the given terms array is nested, directly process the terms.
          if (isset($term->children)) {
            $item['children'] = TermReferenceFancytree::getNestedListJsonArray($term->children, $element, $form_state);
          }
          // It the term has children, but they are not present in the array,
          // mark the item for lazy loading.
          else {
            $item['lazy'] = TRUE;
          }
        }
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * Function that generates a list of vocabulary names in JSON.
   */
  public static function getVocabularyNamesJsonArray($element, $form_state, $ancestors = NULL) {
    $items = [];
    $vocabularies = $element['#vocabulary'];

    if (!empty($vocabularies)) {
      foreach ($vocabularies as $vocabulary) {
        $item = [
          'title' => Html::escape($vocabulary->get('name')),
          'key' => $vocabulary->id(),
          'vocab' => TRUE,
          'unselectable' => TRUE,
          'folder' => TRUE,
        ];

        // Load child terms for the vocabulary.
        $terms = TermReferenceFancytree::loadTerms($vocabulary, 0);

        // Initialize array. (Issue #10).
        $item['lazy'] = FALSE;

        // For each term, check if it's an ancestor of a selected item.
        // If it is, then we need to load the vocabulary folder.
        foreach ($terms as $term) {
          if (isset($ancestors[$term->id()])) {
            $item['lazy'] = FALSE;
            break;
          }
          // Otherwise, we mark it as lazy.
          else {
            $item['lazy'] = TRUE;
          }
        }

        // If the vocabulary folder is loaded, we add the active trail and
        // load its children.
        if (!$item['lazy']) {
          $item['extraClasses'] = 'activeTrail';
          $item['children'] = TermReferenceFancytree::getNestedListJsonArray($terms, $element, $ancestors, $form_state);
        }

        $items[] = $item;

      }
    }
    return $items;
  }

  /**
   * Helper function that returns the number of child terms.
   */
  public static function getChildCount($tid) {
    static $tids = [];

    if (!isset($tids[$tid])) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = Term::load($tid);
      $tids[$tid] = count(static::getTermStorage()
        ->loadTree($term->bundle(), $tid, 1));

    }

    return $tids[$tid];
  }

  /**
   * Function to get term storage.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   The term storage.
   */
  protected static function getTermStorage() {
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  }

}
