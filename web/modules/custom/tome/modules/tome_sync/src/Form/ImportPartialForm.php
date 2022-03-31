<?php

namespace Drupal\tome_sync\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\tome_sync\TomeSyncHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tome_sync\ContentHasherInterface;
use Drupal\tome_sync\ImporterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Contains a form for performing a partial import.
 *
 * @internal
 */
class ImportPartialForm extends FormBase {

  /**
   * The importer.
   *
   * @var \Drupal\tome_sync\ImporterInterface
   */
  protected $importer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content hasher.
   *
   * @var \Drupal\tome_sync\ContentHasherInterface
   */
  protected $contentHasher;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The sync configuration object.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $syncStorage;

  /**
   * The active configuration object.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * Constructs an ImportPartialForm instance.
   *
   * @param \Drupal\tome_sync\ImporterInterface $importer
   *   The importer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tome_sync\ContentHasherInterface $content_hasher
   *   The content hasher.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\Core\Config\StorageInterface $sync_storage
   *   The source storage.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The target storage.
   */
  public function __construct(ImporterInterface $importer, EntityTypeManagerInterface $entity_type_manager, ContentHasherInterface $content_hasher, StateInterface $state, StorageInterface $sync_storage, StorageInterface $active_storage) {
    $this->importer = $importer;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentHasher = $content_hasher;
    $this->state = $state;
    $this->syncStorage = $sync_storage;
    $this->activeStorage = $active_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tome_sync.importer'),
      $container->get('entity_type.manager'),
      $container->get('tome_sync.content_hasher'),
      $container->get('state'),
      $container->get('config.storage.sync'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tome_sync_import_partial_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($error = $this->getInitialError()) {
      $form['error']['#markup'] = '<p>' . $error . '</p>';
      return $form;
    }

    $form['description'] = [
      '#markup' => '<p>' . $this->t("Submitting this form will import all changed Tome content and files. Your local site's content and files will be deleted or updated.") . '</p>',
    ];

    $form['content_status'] = [
      '#type' => 'container',
    ];

    $change_list = $this->contentHasher->getChangelist();

    if (empty($change_list['modified']) && empty($change_list['deleted']) && empty($change_list['added'])) {
      $form['content_status']['status'] = [
        '#markup' => '<p>' . $this->t('No content has been changed or deleted. There may be file changes to import.') . '</p>',
      ];
    }

    if (!empty($change_list['modified'])) {
      $form['content_status']['modified'] = [
        '#title' => $this->t('Modified'),
        '#theme' => 'item_list',
        '#items' => $change_list['modified'],
      ];
    }

    if (!empty($change_list['added'])) {
      $form['content_status']['added'] = [
        '#title' => $this->t('Added'),
        '#theme' => 'item_list',
        '#items' => $change_list['added'],
      ];
    }

    if (!empty($change_list['deleted'])) {
      $form['content_status']['deleted'] = [
        '#title' => $this->t('Deleted'),
        '#theme' => 'item_list',
        '#items' => $change_list['deleted'],
      ];
    }

    if ($this->state->get(ImporterInterface::STATE_KEY_IMPORTING, FALSE)) {
      $form['warning'] = [
        '#markup' => '<p>' . $this->t('<strong>Warning</strong>: Another user may be running an import, proceed only if the last import failed unexpectedly') . '</p>',
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Determines if there is an initial error that should prevent an import.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   An error message, if available.
   */
  protected function getInitialError() {
    if (!$this->contentHasher->hashesExist()) {
      return $this->t('No content hashes exist to perform a partial import. Please run a full Tome install and export (i.e. "drush tome:install && drush tome:export"), which will ensure hashes exist in the database and filesystem.');
    }

    // Inform the user if a config import needs to be made. This has to be done
    // before a content import in case bundles or fields change.
    // @todo Remove temporary third argument when 8.6.x EOLs.
    $storage_comparer = new StorageComparer($this->syncStorage, $this->activeStorage, \Drupal::service('config.manager'));
    if (!empty($this->syncStorage->listAll()) && $storage_comparer->createChangelist()->hasChanges()) {
      $message = $this->t('There are configuration changes that need importing.');
      $url = Url::fromRoute('config.sync', [], [
        'query' => [
          'destination' => Url::fromRoute('tome_sync.import_partial')->toString(),
        ],
      ]);
      // The config module may not be enabled.
      if ($url->access()) {
        $message .= ' ' . $this->t('Please use the <a href=":url">Config Synchronize form</a>, then return here to import content and files.', [
          ':url' => $url->toString(),
        ]);
      }
      else {
        $message .= ' ' . $this->t('Please run a partial config import (i.e. "drush config:import --partial"), then return here to import content and files.');
      }
      return $message;
    }

    // Prevent the current user from being deleted.
    $current_user = $this->currentUser();
    if ($current_user instanceof EntityInterface) {
      $user_name = TomeSyncHelper::getContentName($current_user);
      if (in_array($user_name, $this->contentHasher->getChangelist()['deleted'], TRUE)) {
        return $this->t('This import would delete the current user and cannot be performed using the user interface.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set(ImporterInterface::STATE_KEY_IMPORTING, TRUE);
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Importing changes...'))
      ->setFinishCallback([$this, 'finishCallback']);

    $change_list = $this->contentHasher->getChangelist();

    foreach ($change_list['deleted'] as $name) {
      $batch_builder->addOperation([$this, 'deleteContent'], [$name]);
    }

    $chunked_names = $this->importer->getChunkedNames();
    foreach ($chunked_names as $i => $chunk) {
      foreach ($chunk as $j => $name) {
        if (in_array($name, $change_list['modified'], TRUE) || in_array($name, $change_list['added'], TRUE)) {
          $batch_builder->addOperation([$this, 'importContent'], [$name]);
        }
      }
    }

    $batch_builder->addOperation([$this, 'importFiles']);

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch callback to delete content or a content translation.
   *
   * @param string $name
   *   A content name.
   */
  public function deleteContent($name) {
    list($entity_type_id, $uuid, $langcode) = TomeSyncHelper::getPartsFromContentName($name);
    $results = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties([
      'uuid' => $uuid,
    ]);
    if (count($results) === 1) {
      $entity = reset($results);
      if ($langcode) {
        if ($translation = $entity->getTranslation($langcode)) {
          $entity->removeTranslation($langcode);
          $entity->save();
        }
      }
      else {
        $entity->delete();
      }
    }
  }

  /**
   * Batch callback to import content or add a content translation.
   *
   * @param string $name
   *   A content name.
   */
  public function importContent($name) {
    list($entity_type_id, $uuid, $langcode) = TomeSyncHelper::getPartsFromContentName($name);
    $this->importer->importContent($entity_type_id, $uuid, $langcode);
  }

  /**
   * Batch callback to import files.
   */
  public function importFiles() {
    $this->importer->importFiles();
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether or not the batch was successful.
   * @param mixed $results
   *   Batch results set with context.
   */
  public function finishCallback($success, $results) {
    $this->state->set(ImporterInterface::STATE_KEY_IMPORTING, FALSE);

    if (!$success) {
      $this->messenger()->addError($this->t('Import failed - consult the error log for more details.'));
      return;
    }
    $this->messenger()->addStatus($this->t('Import complete!'));
  }

}
