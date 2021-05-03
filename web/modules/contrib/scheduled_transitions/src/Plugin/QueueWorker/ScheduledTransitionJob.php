<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity;
use Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs a scheduled transition.
 *
 * @QueueWorker(
 *   id = "scheduled_transition_job",
 *   title = @Translation("Scheduled transition job"),
 *   cron = {"time" = 900}
 * )
 */
class ScheduledTransitionJob extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The key in data with the ID of a scheduled transition entity to process.
   */
  const SCHEDULED_TRANSITION_ID = 'scheduled_transition_id';

  /**
   * Executes transitions.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface
   */
  protected $scheduledTransitionsRunner;

  /**
   * Storage for scheduled transitions.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $scheduledTransitionStorage;

  /**
   * Constructs a new ScheduledTransitionJob.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface $scheduledTransitionsRunner
   *   Executes transitions.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, ScheduledTransitionsRunnerInterface $scheduledTransitionsRunner) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->scheduledTransitionStorage = $entityTypeManager->getStorage('scheduled_transition');
    $this->scheduledTransitionsRunner = $scheduledTransitionsRunner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('scheduled_transitions.runner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $id = $data[static::SCHEDULED_TRANSITION_ID];
    $transition = $this->scheduledTransitionStorage->load($id);
    if ($transition) {
      try {
        $this->scheduledTransitionsRunner->runTransition($transition);
      }
      catch (ScheduledTransitionMissingEntity $exception) {
        $transition->delete();
      }
    }
  }

}
