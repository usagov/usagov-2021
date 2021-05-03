<?php

namespace Drupal\workbench_email\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\workbench_email\QueuedEmail;
use Drupal\workbench_email\TemplateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to transition changes to send notification emails.
 */
class WorkbenchTransitionEventSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Event handler for Workbench Moderation.
   *
   * @param \Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent $event
   *   The event listened to.
   */
  public function onWorkbenchModerationTransition($event) {
    $entity = $event->getEntity();
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
    $bundle_entity = $this->entityTypeManager->getStorage($entity->getEntityType()->getBundleEntityType())->load($entity->bundle());
    if (!$event->getStateBefore()) {
      // We need to use the default.
      $from = $bundle_entity->getThirdPartySetting('workbench_moderation', 'default_moderation_state', FALSE);
    }
    else {
      $from = $event->getStateBefore();
    }
    $to = $event->getStateAfter();
    // Load transitions.
    // We don't have the transition available, so we have to load any matching
    // ones.
    if ($transitions = $this->entityTypeManager->getStorage('moderation_state_transition')->loadByProperties([
      'stateFrom' => $from,
      'stateTo' => $to,
    ])) {
      // Filter out any that the user doesn't have access to or that don't have
      // any email templates.
      $transitions = array_filter($transitions, function($transition) {
        /** @var \Drupal\workbench_moderation\ModerationStateTransitionInterface $transition */
        return $this->currentUser->hasPermission(sprintf('use %s transition', $transition->id())) && $transition->getThirdPartySetting('workbench_email', 'workbench_email_templates', []);
      });
      if (!$transitions) {
        // None remain, nothing to do.
        return;
      }
      // There may be multiple at this point, but given we don't have access
      // to the transition that fired this event, we just pick the first one.
      $transition = reset($transitions);
      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = $this->queueFactory->get('workbench_email_send' . PluginBase::DERIVATIVE_SEPARATOR . $entity->getEntityTypeId());

      /** @var \Drupal\workbench_email\TemplateInterface $template */
      foreach ($this->entityTypeManager->getStorage('workbench_email_template')->loadMultiple($transition->getThirdPartySetting('workbench_email', 'workbench_email_templates', [])) as $template) {
        if ($template->getBundles() && !in_array($entity->getEntityTypeId() . ':' . $entity->bundle(), $template->getBundles(), TRUE)) {
          // Continue, invalid bundle.
          continue;
        }
        foreach ($this->prepareRecipients($entity, $template) as $to) {
          $queue->createItem(new QueuedEmail($template, $entity->uuid(), $to));
        }
      }
    }
  }

  /**
   * Event handler for Content Moderation.
   *
   * @param \Drupal\content_moderation\Event\ContentModerationStateChangedEvent|\Drupal\workbench_email\EventSubscriber\ContentModerationStateChangedEvent $event
   *   The event listened to.
   */
  public function onContentModerationTransition($event) {
    $entity = $event->getModeratedEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($event->getWorkflow());
    /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
    $type_plugin = $workflow->getTypePlugin();
    if (!$event->getOriginalState()) {
      $from = $type_plugin->getInitialState($entity)->id();
    }
    else {
      $from = $event->getOriginalState();
    }
    $to = $event->getNewState();
    $templates = $workflow->getThirdPartySetting('workbench_email', 'workbench_email_templates', []);

    try {
      $transition = $type_plugin->getTransitionFromStateToState($from, $to);
    }
    catch (\InvalidArgumentException $e) {
      // Do nothing in case of invalid transition.
      return;
    }

    // Check the transition has a template
    if (!isset($templates[$transition->id()])) {
      return;
    }
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queueFactory->get('workbench_email_send' . PluginBase::DERIVATIVE_SEPARATOR . $entity->getEntityTypeId());

    /** @var \Drupal\workbench_email\TemplateInterface $template */
    foreach ($this->entityTypeManager->getStorage('workbench_email_template')->loadMultiple($templates[$transition->id()]) as $template) {
      if ($template->getBundles() && !in_array($entity->getEntityTypeId() . ':' . $entity->bundle(), $template->getBundles(), TRUE)) {
        // Continue, invalid bundle.
        continue;
      }
      foreach ($this->prepareRecipients($entity, $template) as $to) {
        $queue->createItem(new QueuedEmail($template, $entity->uuid(), $to));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'workbench_moderation.state_transition' => 'onWorkbenchModerationTransition',
      'content_moderation.state_changed' => 'onContentModerationTransition',
    ];
  }

  /**
   * Prepares the recipient list given the entity and template combination.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being transitioned.
   * @param \Drupal\workbench_email\TemplateInterface $template
   *   Template being used.
   *
   * @return array
   *   Array of email addresses to send to.
   */
  protected function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    return $template->getRecipients($entity);
  }

}
