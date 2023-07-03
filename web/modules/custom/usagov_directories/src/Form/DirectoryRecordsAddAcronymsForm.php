<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Implements a form an administrator can use to add acronyms to
 * already-imported directory records.
 * This is expected to be used during development and never again thereafter.
 */
class DirectoryRecordsAddAcronymsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'directory_records_add_acronyms_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Submit this form to add or update the acronyms on records imported from Mothership.'),
    ];
    $form['acronym_file'] = [
      '#type' => 'file',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'], // Does nothing for 'file'
      ],
      '#title' => $this->t('Upload a csv file of "acronym,mothership_uuid"'),
      '#required' => TRUE, // might work in 9.5? https://www.drupal.org/project/drupal/issues/59750
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add (or Update) Acronyms'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    $file = $all_files['acronym_file'];
    if (isset($file)) {
      $filestream = $file->openFile('r');
      $acronyms = [];
      while (!$filestream->eof()) {
        $acronyms[] = $filestream->fgetcsv();
      }
      $form_state->set('acronyms', $acronyms);
    }
    else {
      $form_state->setErrorByName('acronym_file', 'Please select a file to upload!');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $acronyms = $form_state->get('acronyms');
    $firstrow = TRUE;
    foreach ($acronyms as $map_entry) {
      [$acronym, $uuid] = $map_entry;
      if (!$acronym && !$uuid) {
        // blank line, ignore.
        continue;
      }
      $nids = \Drupal::entityQuery('node')->condition('field_mothership_uuid', $uuid)->execute();
      $nid = reset($nids);
      if ($nid) {
        $node = Node::load($nid);
        $node->set('field_acronym', $acronym);
        $node->save();
      }
      else {
        if (!$firstrow) {
          if (!count($nids)) {
            $this->messenger()->addWarning("No node found with mothership_uuid $uuid");
          }
        }
      }
      $firstrow = FALSE;
    }
  }

}
