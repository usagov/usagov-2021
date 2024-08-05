<?php

namespace Drupal\usagov_benefit_category_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to enable or disable showing the benefit search
 * blocks on the homepage and government benefit pages.
 */
class BenefitCategorySearchForm extends FormBase {
  public const TOGGLE_KEY = 'usagov_benefit_category_search.show_block';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'usagov_benefit_category_search_admin_settings_form';
  }

  /**
   * Builds a form to display current state and toggle showing call out blocks.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $toggle_state = \Drupal::state()->get(self::TOGGLE_KEY) ? TRUE : FALSE;

    $description = $toggle_state ?
      "Showing the benefit search blocks is ENABLED" :
      "Showing the benefit search blocks is DISABLED";

    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $description,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t(
        '@able display of benefit search call-out blocks',
        ['@able' => $toggle_state ? 'DISABLE' : 'ENABLE']
      ),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $toggle_state = \Drupal::state()->get(self::TOGGLE_KEY) ? TRUE : FALSE;
    $errors = FALSE;

    try {
      if ($toggle_state) {
        \Drupal::state()->delete(self::TOGGLE_KEY);
      }
      else {
        \Drupal::state()->set(self::TOGGLE_KEY, TRUE);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('usagov_benefit_category_search')
        ->error('Error while attempting toggle benefit search blocks: @error',
        ['@error' => $e->getMessage()]);
      $errors = TRUE;
    }

    if ($errors) {
      $this->messenger()->addError("Something went wrong. See the error log for details.");
    }
  }

  /**
   * Helper to check if showing the blocks is enabled.
   */
  public static function showLandingPageBlock(): bool {
    return \Drupal::state()->get(self::TOGGLE_KEY) ? TRUE : FALSE;
  }

}
