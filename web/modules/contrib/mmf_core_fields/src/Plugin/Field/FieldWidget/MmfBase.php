<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides common functionality for new MMF field widgets.
 */
trait MmfBase {

  /**
   * Shows minimum widgets on the content add form.
   *
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);

    // Set widget count based on configured value at form Manage Form Display.
    $min_count = $this->getSetting('min_count') - 1;
    if ($field_state['items_count'] < $min_count) {
      $field_state['items_count'] = $min_count;
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $elements = parent::formMultipleElements($items, $form, $form_state);

    return $elements;
  }

  /**
   * Sets default value for minimum fields.
   *
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array('min_count' => 1) + parent::defaultSettings();
  }

  /**
   * Populate settings form to input minimum fields value.
   *
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['min_count'] = array(
      '#type' => 'number',
      '#title' => $this->t('Minimum Fields'),
      '#default_value' => $this->getSetting('min_count'),
      '#required' => TRUE,
      '#min' => 1,
      '#description' => t('Set minimum input fields to be available in the node add form.'),
    );

    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * Shows settings summary for fields at form Manage Form Display.
   *
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $min_count = $this->getSetting('min_count');
    if (!empty($min_count)) {
      $summary[] = t('Minimum Fields : @mincount', array('@mincount' => $min_count));
    }
    else {
      $summary[] = t('No minimum field value set');
    }

    return array_merge($summary, parent::settingsSummary());
  }

}
