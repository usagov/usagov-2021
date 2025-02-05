<?php

namespace Drupal\usagov_analytics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Implements an example form.
 */
class USAGovAnalyticsForm extends FormBase {

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

    // if ($form_state->getValue('fav_car')) {
    //   $form['something'] = [
    //     '#type' => 'processed_text',
    //     '#text' => $form_state->getValue('fav_car'),
    //   ];
    // }

    // Description of what this page is.
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => "In following page you can download daily Google Search files for USA.gov and USA.gov/es/ using the Google Search Console API. The results are in a format that can be ingested into Medallia.",
    ];

    // Drop-down input to select the language.
    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('<b>Select Language</b>'),
      '#options' => [
        'english' => $this->t('English'),
        'spanish' => $this->t('Spanish'),
      ],
    ];

    // "Enter a Start Date" input field.
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('<b>Enter a Start Date</b>'),
    ];

    // "Enter an End Date" input field.
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('<b>Enter an End Date</b>'),
    ];

    // Submit button to run the "submitForm" function onClick.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Script'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Getting the date of the "Enter a Start Date" input field.
    $originalStartDate = $form_state->getValue('start_date');
    // Converting starting date to the correct format YYYY-MM-DD.
    $newStartDate = date("Y-m-d", strtotime($originalStartDate));

    // Getting the date of the "Enter an End Date" input field.
    $originalEndDate = $form_state->getValue('end_date');
    // Converting end date to the correct format YYYY-MM-DD.
    $newEndDate = date("Y-m-d", strtotime($originalEndDate));

    // Getting the name of the Language drop-down.
    $language = $form_state->getValue('language');

    // Run the python script.
    // Date format is: YYYY-MM-DD.
    $output = shell_exec("
      cd modules/custom/usagov_analytics/src/Scripts
      chmod +x setup.sh
      sh ./setup.sh $language $newStartDate $newEndDate
    ");

    // Create a file with the output of the python script.
    $response = new Response($output);
    $response->headers->set('Content-Type', 'text/plain');
    $response->headers->set('Content-Disposition', 'attachment; filename="script_output.txt"');

    $response->send();

    // $form_state->setRebuild();
    exit;
  }

}
