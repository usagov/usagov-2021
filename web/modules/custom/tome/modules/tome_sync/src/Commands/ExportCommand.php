<?php

namespace Drupal\tome_sync\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tome_base\CommandBase;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Drupal\tome_sync\ExporterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains the tome:export command.
 *
 * @internal
 */
class ExportCommand extends CommandBase {

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
   * The exporter.
   *
   * @var \Drupal\tome_sync\ExporterInterface
   */
  protected $exporter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs an ExportCommand instance.
   *
   * @param \Drupal\tome_sync\ExporterInterface $exporter
   *   The exporter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ExporterInterface $exporter, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct();
    $this->exporter = $exporter;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:export')
      ->setDescription('Exports all config, content, and files.')
      ->addOption('process-count', NULL, InputOption::VALUE_OPTIONAL, 'Limits the number of processes to run concurrently.', self::PROCESS_COUNT)
      ->addOption('entity-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of entities to export per process.', self::ENTITY_COUNT)
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Assume "yes" as answer to all prompts,');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $options = $input->getOptions();
    if (!$options['yes'] && !$this->io()->confirm('The files in your export directory will be deleted and replaced.', FALSE)) {
      return 0;
    }
    if (!$this->runCommand($this->executable . " config:export -y", NULL, NULL)) {
      return 1;
    }

    if (!$this->exporter->deleteExportDirectories()) {
      $this->io()->error('Unable to delete existing export directories, please delete manually.');
      return 1;
    }
    $entities = $this->exporter->getContentToExport();
    $id_pairs = [];
    $commands = [];
    foreach ($entities as $entity_type_id => $ids) {
      foreach ($ids as $id) {
        $id_pairs[] = "$entity_type_id:$id";
      }
    }
    foreach (array_chunk($id_pairs, $options['entity-count']) as $chunk) {
      $commands[] = $this->executable . ' tome:export-content ' . escapeshellarg(implode(',', $chunk));
    }
    $collected_errors = $this->runCommands($commands, $options['process-count'], 0);
    if (!empty($collected_errors)) {
      $this->io()->error('Errors encountered when exporting content:');
      $this->displayErrors($collected_errors);
      return 1;
    }

    $this->eventDispatcher->dispatch(TomeSyncEvents::EXPORT_ALL, new Event());

    $this->io()->success('Exported config, content, and files.');
  }

}
