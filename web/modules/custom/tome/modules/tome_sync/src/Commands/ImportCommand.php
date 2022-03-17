<?php

namespace Drupal\tome_sync\Commands;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\tome_base\CommandBase;
use Drupal\tome_sync\ImporterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contains the tome:import command.
 *
 * @internal
 */
class ImportCommand extends CommandBase {

  /**
   * The default number of processes to invoke.
   *
   * @todo Increase this once deadlocks do not occur in SQLite. :-(
   *
   * @var int
   */
  const PROCESS_COUNT = 1;

  /**
   * The default number of entities to import in each process.
   *
   * @var int
   */
  const ENTITY_COUNT = 20;

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
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs an ImportCommand instance.
   *
   * @param \Drupal\tome_sync\ImporterInterface $importer
   *   The importer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   */
  public function __construct(ImporterInterface $importer, EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    parent::__construct();
    $this->importer = $importer;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  protected  function configure() {
    $this->setName('tome:import')
      ->setDescription('Imports all config, content, and files.')
      ->addOption('process-count', NULL, InputOption::VALUE_OPTIONAL, 'Limits the number of processes to run concurrently.', self::PROCESS_COUNT)
      ->addOption('entity-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of entities to export per process.', self::ENTITY_COUNT)
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Assume "yes" as answer to all prompts,');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $options = $input->getOptions();

    if (!$options['yes'] && !$this->io()->confirm('Your local site\'s config, content, and files will be deleted and replaced.', FALSE)) {
      return 0;
    }

    if (!$this->checkImportingState($options)) {
      return 0;
    }
    $this->state->set(ImporterInterface::STATE_KEY_IMPORTING, TRUE);

    $delete_content = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        foreach ($this->entityTypeManager->getStorage($entity_type->id())->getQuery()->execute() as $id) {
          $delete_content[] = $entity_type->id() . ':' . $id;
        }
      }
    }
    if (!$this->deleteContent($delete_content, $options['entity-count'], $options['process-count'])) {
      return 1;
    }

    $this->prepareConfigForImport();
    if (!$this->runCommand($this->executable . " config:import -y", NULL, NULL)) {
      return 1;
    }

    $chunked_names = $this->importer->getChunkedNames();
    if (!$this->importChunks($chunked_names, $options['entity-count'], $options['process-count'])) {
      return 1;
    }

    $this->importer->importFiles();

    if (!$this->runCommand($this->executable . " cache:rebuild -y", NULL, NULL)) {
      return 1;
    }

    if (!$this->runCommand($this->executable . " tome:import-complete")) {
      return 1;
    }

    $this->state->set(ImporterInterface::STATE_KEY_IMPORTING, FALSE);

    $this->io()->success('Imported config, content, and files.');
  }

  /**
   * Prepares config for import by copying some directly from the source.
   */
  protected function prepareConfigForImport() {
    /** @var \Drupal\Core\Config\StorageInterface $source_storage */
    $source_storage = \Drupal::service('config.storage.sync');
    if ($site_data = $source_storage->read('system.site')) {
      \Drupal::configFactory()->getEditable('system.site')->setData($site_data)->save(TRUE);
      if (!empty($site_data['default_langcode']) && $language_data = $source_storage->read('language.entity.' . $site_data['default_langcode'])) {
        \Drupal::configFactory()->getEditable('language.entity.' . $site_data['default_langcode'])->setData($language_data)->save(TRUE);
      }
    }
  }

  /**
   * Imports chunks of content using sub-processes.
   *
   * @param array $chunks
   *   An array of arrays of strings.
   * @param int $entity_count
   *   The number of entities to import per process.
   * @param int $process_count
   *   The number of processes to invoke concurrently.
   *
   * @return bool
   *   Whether or not the import completed successful.
   */
  protected function importChunks(array $chunks, $entity_count, $process_count) {
    foreach ($chunks as $chunk) {
      if (empty($chunk)) {
        continue;
      }
      $commands = [];
      foreach (array_chunk($chunk, $entity_count) as $names) {
        $commands[] = $this->executable . ' tome:import-content ' . escapeshellarg(implode(',', $names));
      }
      $collected_errors = $this->runCommands($commands, $process_count, 0);
      if (!empty($collected_errors)) {
        $this->io()->error('Errors encountered when importing content:');
        $this->displayErrors($collected_errors);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Deletes content using sub-processes.
   *
   * @param array $names
   *   An array of content names.
   * @param int $entity_count
   *   The number of entities to import per process.
   * @param int $process_count
   *   The number of processes to invoke concurrently.
   *
   * @return bool
   *   Whether or not the import completed successful.
   */
  protected function deleteContent(array $names, $entity_count, $process_count) {
    $commands = [];
    foreach (array_chunk($names, $entity_count) as $names) {
      $commands[] = $this->executable . ' tome:delete-content ' . escapeshellarg(implode(',', $names));
    }
    $collected_errors = $this->runCommands($commands, $process_count, 0);
    if (!empty($collected_errors)) {
      $this->io()->error('Errors encountered when deleting content:');
      $this->displayErrors($collected_errors);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Checks the importing state and prompts the user if applicable.
   *
   * @param array $options
   *   An array of command line options.
   *
   * @return bool
   *   Whether or not the process should continue.
   */
  protected function checkImportingState(array $options) {
    if ($this->state->get(ImporterInterface::STATE_KEY_IMPORTING, FALSE)) {
      if (!$options['yes'] && !$this->io()->confirm('Another user may be running an import, proceed only if the last import failed unexpectedly. Ignore and continue import?', FALSE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
