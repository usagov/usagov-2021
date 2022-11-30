<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Implements a form an administrator can use to add language toggles to
 * already-imported directory records.
 * This is expected to be used during development and never again thereafter.
 */
class DirectoryRecordsAddTogglesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'directory_records_add_toggles_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Submit this form to add or update the language toggles on records imported from Mothership.'),
    ];
    $form['toggle_map_file'] = [
      '#type' => 'file',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'], // Does nothing for 'file'
      ],
      '#title' => $this->t('Upload a csv file of "mothership_uuid,toggle_mothership_uuid"'),
      // '#required' => TRUE, // might work in 9.5? https://www.drupal.org/project/drupal/issues/59750
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add (or Update) Language Toggles'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    $file = $all_files['toggle_map_file'];
    if (isset($file)) {
      $filestream = $file->openFile('r');
      $toggle_map = [];
      while (!$filestream->eof()) {
        $toggle_map[] = $filestream->fgetcsv();
      }
      $form_state->set('toggle_map', $toggle_map);
    }
    else {
      $form_state->setErrorByName('toggle_map_file', 'Please select a file to upload!');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $toggle_map = $form_state->get('toggle_map');
    $firstrow = TRUE;
    foreach ($toggle_map as $map_entry) {
      [$entity_uuid, $toggle_uuid] = $map_entry;
      if (!$entity_uuid && !$toggle_uuid) {
        // blank line, ignore.
        continue;
      }
      $nids = \Drupal::entityQuery('node')->condition('field_mothership_uuid', $entity_uuid)->execute();
      $toggle_nids = \Drupal::entityQuery('node')->condition('field_mothership_uuid', $toggle_uuid)->execute();
      $nid = reset($nids);
      $toggle_nid = reset($toggle_nids);
      if ($nid && $toggle_nid) {
        $node = Node::load($nid);
        $node->set('field_language_toggle', ['target_id' => $toggle_nid]);
        $node->save();
      }
      else {
        if (!$firstrow) {
          if (!count($nids)) {
            $this->messenger()->addWarning("No node found with mothership_uuid $entity_uuid");
          }
          if (count($toggle_nids)) {
            $this->messenger()->addWarning("No node found with mothership_uuid $entity_uuid (for toggle value)");
          }
        }
      }
      $firstrow = FALSE;
    }
  }

}
