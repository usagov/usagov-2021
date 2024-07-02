<?php

namespace Drupal\usagov_benefit_category_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BenefitCategorySearchForm extends ConfigFormBase {
  public const SETTINGS_KEY = 'usagov_benefit_category_search.settings';
  public const SHOW_LANDING_PAGE_BLOCK = 'homepage_show_benefits_search_block';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'usagov_benefit_category_search_admin_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form[self::SHOW_LANDING_PAGE_BLOCK] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display new Benefits Landing Page block on home and benefits topic page'),
      '#description' => $this->t("Enable to show the new block linking to the landing page instead of the carousel on the homepage"),
      '#return_value' => TRUE,
      '#default_value' => $this->config(self::SETTINGS_KEY)
        ->get(self::SHOW_LANDING_PAGE_BLOCK),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::SETTINGS_KEY)
      ->set(self::SHOW_LANDING_PAGE_BLOCK, $form_state->getValue(self::SHOW_LANDING_PAGE_BLOCK))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @return string[]
   */
  protected function getEditableConfigNames(): array {
    return [
      self::SETTINGS_KEY,
    ];
  }

  public static function showLandingPageBlock(): bool {
    $config = \Drupal::config(BenefitCategorySearchForm::SETTINGS_KEY);
    return $config->get(BenefitCategorySearchForm::SHOW_LANDING_PAGE_BLOCK) === TRUE;
  }

}
