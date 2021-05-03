<?php

namespace Drupal\stepbystep\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stepbystep\Plugin\SequenceInterface;

/**
 * Form to reset the progress of a Step by Step sequence.
 *
 * This form may be used as part of a sequence to reset the progress of another
 * sequence. It is separate from the Reset button that appears on the
 * sequence introduction form.
 */
class SequenceResetForm extends FormBase {

  /**
   * The Step by Step sequence to reset.
   *
   * @var \Drupal\stepbystep\Plugin\SequenceInterface
   */
  protected $sequence;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stepbystep_sequence_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SequenceInterface $sequence = NULL) {
    $this->sequence = $sequence;
    $started = $sequence->isStarted();

    $form['actions'] = ['#type' => 'actions'];
    // Set up the message and submit buttons based on the sequence status.
    // Separate submit buttons are created (rather than just changing the
    // #value text) to make it easier for other sequences to conditionally show
    // this form using 'skip_if_not_present'.
    if ($started) {
      $description = $this->t(
        'Do you wish to reset the %name wizard ready to begin again?',
        ['%name' => $sequence->getName()]
      );
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Yes, reset the wizard'),
        '#button_type' => 'primary',
      ];
    }
    else {
      $description = $sequence->getDescription() ?: $this->t('This wizard is ready to be started');
      $form['actions']['submit-start'] = [
        '#type' => 'submit',
        '#value' => $this->t('Start'),
        '#button_type' => 'primary',
      ];
    }

    $form['#title'] = $sequence->getTitle();
    $form['description'] = [
      '#markup' => '<p>' . $description . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#parents'][0] == 'submit-start') {
      // 'Start' was clicked. Redirect to the first available step.
      $form_state->setRedirectUrl($this->sequence->getNextUrl());
    }
    else {
      // 'Reset' was clicked. Reset the progress and redirect to the
      // introduction form.
      $this->sequence->resetProgress();
      $form_state->setRedirectUrl($this->sequence->getUrl());
    }
  }

}
