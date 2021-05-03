<?php

namespace Drupal\content_lock\Plugin\Action;

use Drupal\content_lock\ContentLock\ContentLock;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action that can break a lock.
 *
 * @Action(
 *   id = "entity:break_lock",
 *   action_label = @Translation("Break Lock"),
 *   deriver = "Drupal\content_lock\Plugin\Action\BreakLockDeriver",
 * )
 */
class BreakLock extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * Content lock service.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLock
   */
  protected $lockService;

  /**
   * Constructs a BreakLock object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_lock\ContentLock\ContentLock $contentLock
   *   Content lock service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentLock $contentLock) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->lockService = $contentLock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_lock')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $this->lockService->release($entity->id(), $entity->language(), NULL, NULL, $entity->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

}
