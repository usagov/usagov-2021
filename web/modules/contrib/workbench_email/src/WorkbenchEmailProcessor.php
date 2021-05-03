<?php

namespace Drupal\workbench_email;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Utility\Error;

/**
 * Defines a class for processing a queue for a given entity ID.
 */
class WorkbenchEmailProcessor {

  /**
   * Time to process for.
   */
  const PROCESSING_TIME = 30;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a WorkbencEmailProcessor object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
    $this->logger = $logger_factory->get('workbench_email');
  }

  /**
   * Process the queue for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to process.
   */
  public function processEntity(EntityInterface $entity) {
    // Make sure every queue exists. There is no harm in trying to recreate
    // an existing queue.
    $queue_name = 'workbench_email_send' . PluginBase::DERIVATIVE_SEPARATOR . $entity->getEntityTypeId();
    $this->queueFactory->get($queue_name)->createQueue();

    $queue_worker = $this->queueManager->createInstance($queue_name, ['entity_type' => $entity->getEntityTypeId()]);
    $queue = $this->queueFactory->get($queue_name);
    $to_release = [];
    $end = time() + static::PROCESSING_TIME;
    while (time() < $end && $item = $queue->claimItem()) {
      if ($item->data instanceof QueuedEmail) {
        // We populate and flush the queue in one request at present, so we
        // should only ever have matching items. However in theory we could
        // support sending in the background, in which case we are defensive
        // here in case there are items in the queue that don't match the entity
        // we're processing. Similarly, there may be an instance where two or
        // more entities are saved in the one request, and in that case there
        // would be more than one entity in the queue.
        if ($item->data->getUuid() === $entity->uuid()) {
          try {
            $queue_worker->processItem($item->data);
            $queue->deleteItem($item);
          }
          catch (\Exception $e) {
            // In case of any exception, just log it.
            $this->logger->log(RfcLogLevel::ERROR, '%type: @message in %function (line %line of %file).', Error::decodeException($e));
          }
        }
        else {
          $to_release[] = $item;
        }
      }
      else {
        throw new \LogicException('Cannot perform queue processing on objects other than a QueuedEmail.');
      }
    }
    // Put these back into the queue.
    foreach ($to_release as $item) {
      $queue->releaseItem($item);
    }
  }

}
