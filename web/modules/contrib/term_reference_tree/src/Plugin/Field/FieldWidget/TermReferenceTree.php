<?php

namespace Drupal\term_reference_tree\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Plugin implementation of the 'term_reference_tree' widget.
 *
 * @FieldWidget(
 *   id = "term_reference_tree",
 *   label = @Translation("Term reference tree"),
 *   field_types = {"entity_reference"},
 *   multiple_values = TRUE
 * )
 */
class TermReferenceTree extends WidgetBase {

  const CASCADING_SELECTION_NONE = '0';

  const CASCADING_SELECTION_BOTH = '1';

  const CASCADING_SELECTION_SELECT = '2';

  const CASCADING_SELECTION_DESELECT = '3';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'start_minimized' => TRUE,
        'leaves_only' => FALSE,
        'select_parents' => FALSE,
        'cascading_selection' => self::CASCADING_SELECTION_NONE,
        'max_depth' => 0,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['start_minimized'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start minimized'),
      '#description' => $this->t('Make the tree appear minimized on the form by default'),
      '#default_value' => $this->getSetting('start_minimized'),
    ];

    $form['leaves_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Leaves only'),
      '#description' => $this->t("Don't allow the user to select items that have children"),
      '#default_value' => $this->getSetting('leaves_only'),
      '#return_value' => 1,
    ];

    $form['select_parents'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select parents automatically'),
      '#description' => $this->t("When turned on, this option causes the widget to automatically select the ancestors of all selected items. In Leaves Only mode, the parents will be added invisibly to the selected value.  <em>This option is only valid if an unlimited number of values can be selected.</em>"),
      '#default_value' => $this->getSetting('select_parents'),
      '#return_value' => 1,
    ];

    $form['cascading_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Cascading selection'),
      '#description' => $this->t('On parent selection, automatically select children if none were selected. Some may then be manually unselected. In the same way, on parent unselection, unselect children if all were selected. <em>This option is only valid if an unlimited number of values can be selected.</em>'),
      '#default_value' => $this->getSetting('cascading_selection'),
      '#options' => [
        self::CASCADING_SELECTION_NONE => $this->t('None'),
        self::CASCADING_SELECTION_BOTH => $this->t('Select / deselect'),
        self::CASCADING_SELECTION_SELECT => $this->t('Only select'),
        self::CASCADING_SELECTION_DESELECT => $this->t('Only deselect'),
      ],
    ];

    if ($this->fieldDefinition->getFieldStorageDefinition()
        ->getCardinality() !== FieldStorageConfig::CARDINALITY_UNLIMITED) {
      $form['select_parents']['#disabled'] = TRUE;
      $form['select_parents']['#default_value'] = FALSE;
      $form['select_parents']['#description'] .= ' <em>' . $this->t("This option is only valid if an unlimited number of values can be selected.") . '</em>';

      $form['cascading_selection']['#disabled'] = TRUE;
      $form['cascading_selection']['#default_value'] = self::CASCADING_SELECTION_NONE;
      $form['cascading_selection']['#description'] .= ' <em>' . $this->t("This option is only valid if an unlimited number of values can be selected.") . '</em>';
    }

    $form['max_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Depth'),
      '#description' => $this->t("Only show items up to this many levels deep."),
      '#default_value' => $this->getSetting('max_depth'),
      '#min' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('start_minimized')) {
      $summary[] = $this->t('Start minimized');
    }

    if ($this->getSetting('leaves_only')) {
      $summary[] = $this->t('Leaves only');
    }

    if ($this->getSetting('select_parents')) {
      $summary[] = $this->t('Select parents automatically');
    }

    $cascadingSelection = $this->getSetting('cascading_selection');
    if ($cascadingSelection == self::CASCADING_SELECTION_BOTH) {
      $summary[] = $this->t('Cascading selection');
    }
    elseif ($cascadingSelection == self::CASCADING_SELECTION_SELECT) {
      $summary[] = sprintf('%s (%s)', $this->t('Cascading selection'), $this->t('Only select'));
    }
    elseif ($cascadingSelection == self::CASCADING_SELECTION_DESELECT) {
      $summary[] = sprintf('%s (%s)', $this->t('Cascading selection'), $this->t('Only deselect'));
    }

    if ($this->getSetting('max_depth')) {
      $summary[] = $this->formatPlural($this->getSetting('max_depth'), 'Maximum Depth: @count level', 'Maximum Depth: @count levels');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $handler_settings = $this->getFieldSetting('handler_settings');
    $vocabularies = Vocabulary::loadMultiple($handler_settings['target_bundles']);

    $element['#type'] = 'checkbox_tree';
    $element['#default_value'] = $items->getValue();
    $element['#vocabularies'] = $vocabularies;
    $element['#max_choices'] = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();
    $element['#leaves_only'] = $this->getSetting('leaves_only');
    $element['#select_parents'] = $this->getSetting('select_parents');
    $element['#cascading_selection'] = $this->getSetting('cascading_selection');
    $element['#value_key'] = 'target_id';
    $element['#max_depth'] = $this->getSetting('max_depth');
    $element['#start_minimized'] = $this->getSetting('start_minimized');
    $element['#element_validate'] = [
      [
        get_class($this),
        'validateTermReferenceTreeElement',
      ],
    ];
    return $element;
  }

  /**
   * Form element validation handler for term reference form widget.
   */
  public static function validateTermReferenceTreeElement(&$element, FormStateInterface $form_state) {
    $items = _term_reference_tree_flatten($element, $form_state);
    $value = [];
    if ($element['#max_choices'] != 1) {
      foreach ($items as $child) {
        if (!empty($child['#value'])) {
          // If the element is leaves only and select parents is on,
          // then automatically add all the parents of each selected value.
          if (!empty($element['#select_parents']) && !empty($element['#leaves_only'])) {
            foreach ($child['#parent_values'] as $parent_tid) {
              if (!in_array([$element['#value_key'] => $parent_tid], $value)) {
                array_push($value, [$element['#value_key'] => $parent_tid]);
              }
            }
          }
          array_push($value, [$element['#value_key'] => $child['#value']]);
        }
      }
    }
    else {
      // If it's a tree of radio buttons, they all have the same value,
      // so we can just grab the value of the first one.
      if (count($items) > 0) {
        $child = reset($items);
        if (!empty($child['#value'])) {
          array_push($value, [$element['#value_key'] => $child['#value']]);
        }
      }
    }
    if ($element['#required'] && empty($value)) {
      $form_state->setError($element, t('%name field is required.', ['%name' => $element['#title']]));
    }
    $form_state->setValueForElement($element, $value);
  }

}
