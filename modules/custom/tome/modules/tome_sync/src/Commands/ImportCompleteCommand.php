<?php

namespace Drupal\tome_sync\Commands;

use Drupal\tome_base\CommandBase;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains the tome:import-complete command.
 *
 * @internal
 */
class ImportCompleteCommand extends CommandBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs an ImportCompleteCommand instance.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    parent::__construct();
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  protected  function configure() {
    $this->setName('tome:import-complete')
      ->setDescription('Triggers an import complete event.')
      ->setHidden(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->eventDispatcher->dispatch(TomeSyncEvents::IMPORT_ALL, new Event());
  }

}
