<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

//TODO: Handle character entities properly (e.g., &#151; for em dash)

/**
 * Implements a form an administrator can use to add language toggles to
 * already-imported directory records.
 * This is expected to be used during development and never again thereafter.
 */
class DirectoryRecordsAddSynonymsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'directory_records_add_synonyms_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Submit this form to add Synonyms for records imported from Mothership. Multiple synonyms for a node maybe supplied as a single string joined with "###".'),
    ];
    $form['synonym_file'] = [
      '#type' => 'file',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'], // Does nothing for 'file'
      ],
      '#title' => $this->t('Upload a csv file of "mothership_uuid,langcode,synonyms"'),
      // '#required' => TRUE, // might work in 9.5? https://www.drupal.org/project/drupal/issues/59750
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Synonyms'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    $file = $all_files['synonym_file'];
    if (isset($file)) {
      $filestream = $file->openFile('r');
      $synonym_map = [];
      while (!$filestream->eof()) {
        $synonym_map[] = $filestream->fgetcsv();
      }
      $form_state->set('synonym_map', $synonym_map);
    }
    else {
      $form_state->setErrorByName('synonym_file', 'Please select a file to upload!');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $synonym_map = $form_state->get('synonym_map');
    $firstrow = TRUE;
    foreach ($synonym_map as $map_entry) {
      [$entity_uuid, $langcode, $synonyms_str] = $map_entry;
      if (!$entity_uuid) {
        // probably blank line, ignore.
        continue;
      }
      $synonyms = explode('###', $synonyms_str);
      $nids = \Drupal::entityQuery('node')->condition('field_mothership_uuid', $entity_uuid)->execute();
      $nid = reset($nids);
      $synonym_count = $node_count = 0;

      if ($nid) {
        $node_count++;
        foreach ($synonyms as $synonym_title) {
          // Check for an existing synonym:
          $existing_nids = \Drupal::entityQuery('node')->condition('type', 'agency_synonym')
            ->condition('title', $synonym_title)->execute();
          if (count($existing_nids) === 0) {
            $attrs = [
              'type' => 'agency_synonym',
              'title' => $synonym_title,
              'langcode' => $langcode,
              'field_agency_reference' => ['target_id' => $nid],
            ];
            $syn_node = \Drupal::entityTypeManager()->getStorage('node')->create($attrs);
            $syn_node->save();
            $synonym_count++;
          }
        }
        // $node = Node::load($nid);
        // $node->set('field_language_toggle', ['target_id' => $toggle_nid]);
        // $node->save();
      }
      else {
        if (!$firstrow) {
          if (!count($nids)) {
            $this->messenger()->addWarning("No node found with mothership_uuid $entity_uuid");
          }
        }
      }
      $firstrow = FALSE;
    }
    $this->messenger()->addMessage("$synonym_count Synonyms created for $node_count Directory Records.");
  }

}
