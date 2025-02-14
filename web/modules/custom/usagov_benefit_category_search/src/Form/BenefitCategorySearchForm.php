<?php

namespace Drupal\usagov_benefit_category_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to enable or disable showing the benefit search
 * blocks on the homepage and government benefit pages.
 */
class BenefitCategorySearchForm extends FormBase {
  public const TOGGLE_KEY = 'usagov_benefit_category_search.show_block';

  public function __construct(
    private StateInterface $state,
    private LoggerInterface $log_channel,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      state: $container->get('state'),
      log_channel: $container->get('logger.factory')->get('usagov_benefit_category_search'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'usagov_benefit_category_search_admin_settings_form';
  }

  /**
   * Builds a form to display current state and toggle showing call out blocks.
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $toggle_state = $this->state->get(self::TOGGLE_KEY) ? TRUE : FALSE;

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
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $toggle_state = $this->state->get(self::TOGGLE_KEY) ? TRUE : FALSE;
    $errors = FALSE;

    try {
      if ($toggle_state) {
        $this->state->delete(self::TOGGLE_KEY);
      }
      else {
        $this->state->set(self::TOGGLE_KEY, TRUE);
      }
    }
    catch (\Exception $e) {
      $this->log_channel->error(
        'Error while attempting toggle benefit search blocks: @error',
        ['@error' => $e->getMessage()]
      );
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
