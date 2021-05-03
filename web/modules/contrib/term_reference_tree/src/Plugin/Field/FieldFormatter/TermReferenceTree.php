<?php

namespace Drupal\term_reference_tree\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the thumbnail field formatter.
 *
 * @FieldFormatter(
 *   id = "term_reference_tree",
 *   label = @Translation("Term Reference Tree"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class TermReferenceTree extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element[] = array(
      '#theme' => 'term_tree_list',
      '#data' => $items->getValue(),
      '#attached' => ['library' => ['term_reference_tree/term_reference_tree_css']],
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

}
