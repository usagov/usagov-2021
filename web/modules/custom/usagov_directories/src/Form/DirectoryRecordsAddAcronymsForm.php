<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a form an administrator can use to add acronyms to
 * already-imported directory records.
 * This is expected to be used during development and never again thereafter.
 */
class DirectoryRecordsAddAcronymsForm extends FormBase {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      entityTypeManager: $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'directory_records_add_acronyms_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
   *
   * @param array<string, mixed> $form
   */
  #[\Override]
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $acronyms = $form_state->get('acronyms');
    $firstrow = TRUE;
    foreach ($acronyms as $map_entry) {
      [$acronym, $uuid] = $map_entry;
      if (!$acronym && !$uuid) {
        // blank line, ignore.
        continue;
      }
      $nids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('field_mothership_uuid', $uuid)
        ->accessCheck(TRUE)
        ->execute();
      $nid = reset($nids);
      if ($nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
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
