<?php

namespace Drupal\term_reference_tree\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for term reference tree.
 *
 * @FormElement("checkbox_tree")
 */
class CheckboxTree extends FormElement {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processCheckboxTree'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCompositeFormElement'],
      ],
      '#default_value' => [],
      '#attached' => [
        'library' => [
          'term_reference_tree/term_reference_tree_js',
          'term_reference_tree/term_reference_tree_css',
        ],
      ],
      '#theme' => 'checkbox_tree',
    ];
  }

  /**
   * Processes a checkboxes form element.
   */
  public static function processCheckboxTree(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = is_array($element['#value']) ? $element['#value'] : [];
    $allowed = [];
    $filter = FALSE;
    if (!empty($element['#max_choices']) && $element['#max_choices'] != '-1') {
      $element['#attached']['drupalSettings'] = [
        'term_reference_tree' => [
          'trees' => [
            $element['#id'] => [
              'max_choices' => $element['#max_choices'],
            ],
          ],
        ],
      ];
    }
    if (!empty($element['#allowed'])) {
      $allowed = $element['#allowed'];
      $filter = TRUE;
    }

    if (empty($element['#options'])) {
      $options_tree = [];
      foreach ($element['#vocabularies'] as $vocabulary) {
        $options = _term_reference_tree_get_term_hierarchy(0, $vocabulary->id(), $allowed, $filter, '', $value);
        $options_tree = array_merge($options_tree, $options);
      }
      $element['#options_tree'] = $options_tree;
      $element['#options'] = _term_reference_tree_get_options($element['#options_tree'], $allowed, $filter);
    }

    $terms = !empty($element['#options_tree']) ? $element['#options_tree'] : [];

    $tree = new \stdClass();
    $tree->children = $terms;
    unset($element['#needs_validation']);
    $element[] = _term_reference_tree_build_level($element, $tree, $form_state, $value, $element['#max_choices'], [], 1);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $value = [];
    $element += ['#default_value' => []];
    foreach ($element['#default_value'] as $key) {
      if (isset($key['target_id'])) {
        $value[$key['target_id']] = $key['target_id'];
      }
    }
    return $value;
  }

}
