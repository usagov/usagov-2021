<?php

namespace Drupal\stepbystep\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stepbystep\Plugin\SequenceInterface;

/**
 * Form to display at the beginning of a Step by Step sequence.
 *
 * Contains a button to proceed to the sequence, and advanced details containing
 * a summary of the current progress of the sequence.
 */
class SequenceIntroductionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stepbystep_sequence_introduction_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SequenceInterface $sequence = NULL) {
    $form['#title'] = $sequence->getTitle();
    // Show the Start/Resume button if there are remaining steps to be done.
    if ($sequence->hasStepsRemaining()) {
      $form['actions'] = [
        '#type' => 'actions',
        '#weight' => -10,
        'submit' => [
          '#type' => 'submit',
          '#value' => $sequence->isStarted() ? $this->t('Resume') : $this->t('Start'),
          '#button_type' => 'primary',
        ],
      ];
    }
    $description = $sequence->getDescription();
    if (!empty($description)) {
      $form['description'] = ['#markup' => '<p>' . $description . '</p>'];
    }
    $form['advanced'] = $this->buildAdvancedOptions($sequence);
    $form['#attached']['library'][] = 'stepbystep/stepbystep.css';
    // Allow the sequence plugin to alter its introduction form if desired.
    $sequence->alterIntroductionForm($form, $form_state);
    return $form;
  }

  /**
   * Builds the Advanced Options section of the form.
   *
   * @param \Drupal\stepbystep\Plugin\SequenceInterface $sequence
   *   The sequence to build the options form for.
   *
   * @return array
   *   The Advanced Options render array.
   */
  protected function buildAdvancedOptions(SequenceInterface $sequence) {
    $steps = $sequence->getSteps();
    foreach ($steps as $step_id => &$step) {
      // In the list of steps, display each step using a link that will redirect
      // to the page for the step. Adding the redirect is desirable so the
      // SequenceController will have an opportunity to reset the status of all
      // 'n/a' steps before the user enters the wizard, so they will be re-tried
      // once.
      $step['url'] = $sequence->userAccess($step_id)
        ? $sequence->getUrl($step_id, TRUE)
        : NULL;
    }
    $has_steps_remaining = $sequence->hasStepsRemaining();
    $advanced = [
      '#type' => 'details',
      '#title' => $has_steps_remaining ? $this->t('Advanced options') : $this->t('Details'),
      '#open' => !$has_steps_remaining,
      '#attributes' => [
        'class' => ['stepbystep-details'],
      ],
    ];
    $advanced['steps'] = [
      '#type' => 'fieldset',
    ];
    $advanced['steps']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start again from the beginning'),
      '#access' => $sequence->isStarted(),
      '#submit' => ['::submitReset'],
      '#attributes' => [
        'class' => ['stepbystep-details-reset'],
      ],
    ];
    // Set up variables for the sequence progress theme function.
    $advanced['steps']['progress'] = [
      '#theme' => 'stepbystep_sequence_progress',
      '#caption' => $has_steps_remaining ? $this->t('Progress so far through this wizard') : NULL,
      '#steps' => $steps,
      '#progress' => $sequence->getProgress(),
    ];
    // Add any advanced links provided by the sequence plugin.
    $advanced['links'] = $sequence->getAdvancedLinks();
    return $advanced;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sequence = $form_state->getBuildInfo()['args'][0];
    // Reset all the steps marked 'n/a' so that they get re-tried once.
    $sequence->resetProgress(SequenceInterface::NOT_APPLICABLE);
    // Redirect to the first step that needs to be done.
    $form_state->setRedirectUrl($sequence->getNextUrl());
    // Allow the sequence plugin to handle submission of the introduction form
    // submission if desired.
    $sequence->submitIntroductionForm($form, $form_state);
  }

  /**
   * Submit handler for the Reset Sequence button.
   *
   * Resets the status of the current sequence so it can be started over.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitReset(array &$form, FormStateInterface $form_state) {
    $sequence = $form_state->getBuildInfo()['args'][0];
    $sequence->resetProgress(FALSE);
  }

}
