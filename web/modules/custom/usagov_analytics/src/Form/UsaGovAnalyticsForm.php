<?php

namespace Drupal\usagov_analytics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implements an example form.
 */
class UsaGovAnalyticsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'python_script_runner_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Run the python script.
    $output = shell_exec('
    cd modules/custom/usagov_analytics/src/Scripts
    chmod +x setup.sh
    ls
    sh ./setup.sh
    ');

    // Create a file response.
    $response = new Response($output);
    $response->headers->set('Content-Type', 'text/plain');
    $response->headers->set('Content-Disposition', 'attachment; filename="script_output.txt"');

    $response->send();
    exit;
  }

}
