<?php

namespace Drupal\samlauth\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a multi-value form element.
 *
 * Shamelessly copied from multivalue_form_element, on who we'll depend in 4.x.
 * (At the time of adding this, they seemed to have issues with D9
 * compatibility.)
 *
 * Properties:
 * - #cardinality: the cardinality of this element. Can be a positive number or
 *   MultiValue::CARDINALITY_UNLIMITED to set it as unlimited. The default value
 *   is unlimited.
 * - #add_empty: Applicable only for unlimited cardinality: 0 to add no extra
 *   empty sets values below existing values. FALSE to add no extra empty
 *   values, except display one when there are no existing values. By default,
 *   one empty value is added.
 * - #add_more_label: the label to use for the "add more" button. The default
 *   value is "Add another item".
 *
 * Use this element as a wrapper for other form elements. They will be repeated
 * based on the cardinality specified, organised under a "delta", similar to
 * field widgets. Deltas are sortable.
 * Example of an element that allows to specify unlimited job title strings:
 * @code
 * $form['job_titles'] = [
 *   '#type' => 'multivalue',
 *   '#title' => $this->t('Job titles'),
 *   'title' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Job title'),
 *     '#title_display' => 'invisible',
 *   ],
 * ];
 * @endcode
 *
 * Example of an element with multiple form elements inside. Each "delta" will
 * contain all the children of the main element. This example allows to specify
 * up to three pairs of name/e-mail values:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'multivalue',
 *   '#title' => $this->t('Contacts'),
 *   '#cardinality' => 3,
 *   'name' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Name'),
 *   ],
 *   'mail' => [
 *     '#type' => 'email',
 *     '#title' => $this->t('E-mail'),
 *   ],
 * ];
 * @endCode
 *
 * Default values can be set to the multi-value form element. Never set them in
 * child elements as they will be overridden.
 * Pass the default values keyed by their delta:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'multivalue',
 *   '#default_value' => [
 *     0 => ['name' => 'Bob', 'mail' => 'bob@example.com'],
 *     1 => ['name' => 'Ted', 'mail' => 'ted@example.com'],
 *   ],
 *   ...
 * ];
 * @endCode
 *
 * If only one child element is present, said child element name can be omitted
 * from the default value array:
 * @code
 * $form['job_titles'] = [
 *   '#type' => 'multivalue',
 *   '#title' => $this->t('Job titles'),
 *   'title' => [
 *     ...
 *   ],
 *   '#default_value' => [
 *     'Foo',
 *     'Bar',
 *   ],
 * ];
 * @endcode
 * Note that the values in the form state will always have the full array
 * structure, including the child element name.
 *
 * The element can be marked as required. The required will apply *only* to the
 * first delta. This behaviour is consistent with entity fields.
 * How child elements are marked as required depends on their own #required
 * property.
 * Given the multi-value element is marked as required:
 * - if no children is marked as required, all the children of the first delta
 *   will be set as required.
 * - if any children is marked as required, then the required status specified
 *   for the children will be retained for the first delta.
 * For all the deltas after the first, or when the main element is not marked
 * as required, the #required property of the child elements will be set to
 * FALSE.
 *
 * Example of specifying only some elements are required:
 * @code
 * $form['contacts'] = [
 *   '#type' => 'multivalue',
 *   '#title' => $this->t('Contacts'),
 *   '#required' => TRUE,
 *   'name' => [
 *     '#type' => 'textfield',
 *     '#title' => $this->t('Name'),
 *     '#required' => TRUE,
 *   ],
 *   'mail' => [
 *     '#type' => 'email',
 *     '#title' => $this->t('E-mail'),
 *   ],
 * ];
 * @endCode
 *
 * If you want to have some children required in all the deltas, use #states
 * to mark the wanted elements as required if one of the other children is
 * filled.
 *
 * @FormElement("samlmultivalue")
 */
class MultiValue extends FormElement {

  /**
   * Value indicating that an instance of this element accepts unlimited values.
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#theme' => 'field_multiple_value_form',
      '#cardinality_multiple' => TRUE,
      '#description' => NULL,
      '#cardinality' => self::CARDINALITY_UNLIMITED,
      '#add_more_label' => $this->t('Add another item'),
      '#process' => [
        [$class, 'processMultiValue'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateMultiValue'],
      ],
    ];
  }

  /**
   * Processes a multi-value form element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element_name = end($element['#array_parents']);
    $parents = $element['#parents'];
    $cardinality = $element['#cardinality'];

    $element['#tree'] = TRUE;
    $element['#field_name'] = $element_name;

    $element_state = static::getElementState($parents, $element_name, $form_state);
    if ($element_state === NULL) {
      // The default value should always have numeric keys. The initial count
      // is based on the default value... except if #add_empty says to add an
      // extra item only for 0 values.
      if (!$element['#default_value'] && isset($element['#add_empty']) && $element['#add_empty'] === FALSE) {
        $element_state = ['items_count' => 1];
      }
      else {
        $element_state = [
          'items_count' => count($element['#default_value'] ?? []),
        ];
      }
      static::setElementState($parents, $element_name, $form_state, $element_state);
    }

    // Determine the number of elements to display.
    if ($cardinality !== self::CARDINALITY_UNLIMITED) {
      $nr_elements = $cardinality;
    }
    elseif (!empty($element['#disabled']) || (isset($element['#add_empty']) && !$element['#add_empty'])) {
      $nr_elements = $element_state['items_count'];
    }
    else {
      $nr_elements = $element_state['items_count'] + 1;
    }

    // Extract the elements that will have to be repeated for each delta.
    $children = [];
    foreach (Element::children($element) as $child) {
      $children[$child] = $element[$child];
      unset($element[$child]);
    }

    $value = is_array($element['#value']) ? $element['#value'] : [];
    // Re-key the elements so that deltas are consecutive.
    $value = array_values($value);

    for ($i = 0; $i < $nr_elements; $i++) {
      $element[$i] = $children;

      if (isset($value[$i])) {
        static::setDefaultValue($element[$i], $value[$i]);
      }

      static::setRequiredProperty($element[$i], $i, $element['#required']);

      $element[$i]['_weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for row @number', ['@number' => $i + 1]),
        '#title_display' => 'invisible',
        '#default_value' => $i,
        '#weight' => 100,
      ];
    }

    if ($cardinality === self::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
      $id_prefix = implode('-', $parents);
      $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
      $element['#prefix'] = '<div id="' . $wrapper_id . '">';
      $element['#suffix'] = '</div>';
      $element['add_more'] = [
        '#type' => 'submit',
        '#name' => strtr($id_prefix, '-', '_') . '_add_more',
        '#value' => $element['#add_more_label'],
        '#attributes' => ['class' => ['multivalue-add-more-submit']],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'addMoreAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
    }

    return $element;
  }

  /**
   * Validates a multi-value form element.
   *
   * Used to clean and sort the submitted values in the form state.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input_exists = FALSE;
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);

    if (!$input_exists) {
      return;
    }

    // Remove the 'value' of the 'add more' button.
    unset($values['add_more']);

    // Sort the values based on the weight.
    usort($values, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    foreach ($values as $delta => &$delta_values) {
      // Remove the weight element value from the submitted data.
      unset($delta_values['_weight']);

      // Determine if all the elements of this delta are empty.
      $is_empty_delta = array_reduce($delta_values, function (bool $carry, $value): bool {
        if (is_array($value)) {
          return $carry && empty(array_filter($value));
        }
        else {
          return $carry && ($value === NULL || $value === '');
        }
      }, TRUE);

      // If all the elements are empty, drop this delta.
      if ($is_empty_delta) {
        unset($values[$delta]);
      }
    }

    // Re-key the elements so that deltas are consecutive.
    $values = array_values($values);

    // Set the value back to the form state.
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      return $input;
    }

    $value = [];
    $element += ['#default_value' => []];

    $children_keys = Element::children($element, FALSE);
    $first_child = reset($children_keys);
    $children_count = count($children_keys);

    foreach ($element['#default_value'] as $delta => $default_value) {
      // Enforce numeric deltas.
      if (!is_numeric($delta)) {
        continue;
      }

      // Allow to omit the child element name when one single child exists and
      // the values are simple literals. This allows to pass
      // [0 => 'value 1', 1 => 'value 2'] instead of
      // [0 => ['element_name' => 'value 1', 1 => ['element_name' => ...]].
      if ($children_count === 1 && !is_array($default_value)) {
        $value[$delta] = [$first_child => $default_value];
      }
      else {
        $value[$delta] = $default_value;
      }
    }

    return $value;
  }

  /**
   * Handles the "Add another item" button AJAX request.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreSubmit()
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $element_name = $element['#field_name'];
    $parents = $element['#parents'];

    // Increment the items count.
    $element_state = static::getElementState($parents, $element_name, $form_state);
    $element_state['items_count']++;
    static::setElementState($parents, $element_name, $form_state, $element_state);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|null
   *   The element.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreAjax()
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): ?array {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return NULL;
    }

    // Adding until #1091852 gets solved.
    if (!empty($element['#description_suffix'])) {
      $element['#description'] .= ' ' . $element['#description_suffix'];
    }
    return $element;
  }

  /**
   * Sets the default value for the child elements.
   *
   * @param array $elements
   *   The elements array.
   * @param array $value
   *   An array of values, keyed by the children element name.
   */
  public static function setDefaultValue(array &$elements, array $value): void {
    // @todo Handle nested elements.
    foreach (Element::children($elements, FALSE) as $child) {
      if (isset($value[$child])) {
        $elements[$child]['#default_value'] = $value[$child];
      }
    }
  }

  /**
   * Sets the required property for the delta being processed.
   *
   * @param array $elements
   *   The array containing the child elements.
   * @param int $delta
   *   The delta currently being processed.
   * @param bool $required
   *   If the main element is required or not.
   */
  protected static function setRequiredProperty(array &$elements, int $delta, bool $required): void {
    if ($delta === 0 && $required) {
      // If any of the children is set as required, the first delta is already
      // set correctly.
      foreach ($elements as $element) {
        if (isset($element['#required']) && $element['#required'] === TRUE) {
          return;
        }
      }

      // Set all children as required otherwise.
      foreach ($elements as &$element) {
        $element['#required'] = TRUE;
      }

      return;
    }

    // For every other delta or when the main element is marked as not required,
    // none of the children should be required neither.
    foreach ($elements as &$element) {
      $element['#required'] = FALSE;
    }
  }

  /**
   * Retrieves processing information about the element from $form_state.
   *
   * This method is static so that it can be used in static Form API callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The field name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array with the following key/value pairs:
   *   - items_count: The number of sub-elements to display for the element.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetState()
   */
  public static function getElementState(array $parents, string $element_name, FormStateInterface $form_state): ?array {
    return NestedArray::getValue($form_state->getStorage(), static::getElementStateParents($parents, $element_name));
  }

  /**
   * Stores processing information about the element in $form_state.
   *
   * This method is static so that it can be used in static Form API #callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_state
   *   The array of data to store. See getElementState() for the structure and
   *   content of the array.
   *
   * @see \Drupal\Core\Field\WidgetBase::setWidgetState()
   */
  public static function setElementState(array $parents, string $element_name, FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), static::getElementStateParents($parents, $element_name), $field_state);
  }

  /**
   * Returns the location of processing information within $form_state.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   *
   * @return array
   *   The location of processing information within $form_state.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetStateParents()
   */
  protected static function getElementStateParents(array $parents, string $element_name): array {
    // phpcs:disable
    // Element processing data is placed at
    // $form_state->get(['multivalue_form_element_storage', '#parents', ...$parents..., '#elements', $element_name]),
    // to avoid clashes between field names and $parents parts.
    // phpcs:enable
    return array_merge(
      ['multivalue_form_element_storage', '#parents'],
      $parents,
      ['#elements', $element_name]
    );
  }

}
