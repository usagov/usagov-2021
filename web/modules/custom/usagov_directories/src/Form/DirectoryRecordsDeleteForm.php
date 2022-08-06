<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
  * Implements a form an administrator can use to delete Directory Records.
 * This is expected to be used during development and never again thereafter.
 */
class DirectoryRecordsDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'directory_records_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Hitting "Delete" will delete ALL Federal Directory Records. Are you sure?'),
    ];
    $form['confirm_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check this box to Confirm!'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All Federal Directory Records'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('confirm_delete')) {
      $form_state->setErrorByName('confirm_delete', 'You must check the box. Are you really sure?');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the Federal Directory Records:
    $nids = \Drupal::entityQuery('node')->condition('type', 'directory_record')->execute();
    $count = 0;
    $errors = FALSE;
    foreach ($nids as $nid) {
      try {
        $node = Node::load($nid);
        $node->delete();
        $count++;
      }
      catch (\Exception $e) {
        \Drupal::logger('usagov_directories')->error('Error while attempting to delete record with nid @nid: @error',
          ['@nid' => $nid, '@error' => $e->getMessage()]);
        $errors = TRUE;
      }
    }
    if ($errors) {
      $this->messenger()->addError("Something went wrong. See the error log for details.");
    }
    $this->messenger()->addStatus($this->t("Deleted @count directory records. I hope you're happy with yourself.",
      ['@count' => $count]));
  }

}
