<?php

namespace Drupal\usagov_ssg_postprocessing\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a form an administrator can use Enable or Disable Tome's cron runs.
* This is expected to be used during deployments, allowing config changes to be
 * made before tome's first run.
*/
class ToggleStaticSiteGenerationForm extends FormBase {

  public function __construct(
    private StateInterface $state,
    private LoggerInterface $log_channel,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      state: $container->get('state'),
      log_channel: $container->get('logger.factory')->get('usagov_ssg_postprocessing'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return usagov_ssg_postprocessing_get_static_state_form_id();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $toggle_state = $this->state->get(usagov_ssg_postprocessing_get_static_state_var()) ? 'Enable' : 'Disable';

    $desc_text = $this->state->get(usagov_ssg_postprocessing_get_static_state_var()) ?
      $this->t("Static Site Generation is currently DISABLED.") :
      $this->t("Static Site Generation is currently ENABLED. Note: Disabling will not cancel a Tome run that is already in progress.");

    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $desc_text,
    ];

    /*$form[usagov_ssg_postprocessing_get_static_state_button_name()] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Check this box to ENABLE Static Site Generation.  Uncheck to DISABLE.'),
    '#default_value' => $toggle_state,
    ];*/

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('@able Static Site Generation', ['@able' => $toggle_state]),
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

    $errors = FALSE;

    try {
      $toggle_state = $this->state->get(usagov_ssg_postprocessing_get_static_state_var()) ? FALSE : TRUE;
      if ($toggle_state) {
        $this->state->set(usagov_ssg_postprocessing_get_static_state_var(), TRUE);
      }
      else {
        $this->state->delete(usagov_ssg_postprocessing_get_static_state_var());
      }
    }
    catch (\Exception $e) {
      $this->log_channel->error('Error while attempting toggle tome: @error',
        ['@error' => $e->getMessage()]);
      $errors = TRUE;
    }

    if ($errors) {
      $this->messenger()->addError("Something went wrong. See the error log for details.");
    }
  }

}
