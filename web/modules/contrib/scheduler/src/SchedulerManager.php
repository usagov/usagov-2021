<?php

namespace Drupal\scheduler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\scheduler\Exception\SchedulerMissingDateException;
use Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a scheduler manager.
 */
class SchedulerManager {

  use StringTranslationTrait;

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Scheduler Logger service object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity Type Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a SchedulerManager object.
   */
  public function __construct(DateFormatterInterface $dateFormatter, LoggerInterface $logger, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EventDispatcherInterface $eventDispatcher, TimeInterface $time) {
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
  }

  /**
   * Publish scheduled nodes.
   *
   * @return bool
   *   TRUE if any node has been published, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerMissingDateException
   * @throws \Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException
   */
  public function publish() {
    $result = FALSE;
    $action = 'publish';

    // Select all nodes of the types that are enabled for scheduled publishing
    // and where publish_on is less than or equal to the current time.
    $nids = [];
    $scheduler_enabled_types = array_keys(_scheduler_get_scheduler_enabled_node_types($action));
    if (!empty($scheduler_enabled_types)) {
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->exists('publish_on')
        ->condition('publish_on', $this->time->getRequestTime(), '<=')
        ->condition('type', $scheduler_enabled_types, 'IN')
        ->latestRevision()
        ->sort('publish_on')
        ->sort('nid');
      // Disable access checks for this query.
      // @see https://www.drupal.org/node/2700209
      $query->accessCheck(FALSE);
      $nids = $query->execute();
    }

    // Allow other modules to add to the list of nodes to be published.
    $nids = array_unique(array_merge($nids, $this->nidList($action)));

    // Allow other modules to alter the list of nodes to be published.
    $this->moduleHandler->alter('scheduler_nid_list', $nids, $action);

    // In 8.x the entity translations are all associated with one node id
    // unlike 7.x where each translation was a separate node. This means that
    // the list of node ids returned above may have some translations that need
    // processing now and others that do not.
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->loadNodes($nids);
    foreach ($nodes as $node_multilingual) {

      // The API calls could return nodes of types which are not enabled for
      // scheduled publishing, so do not process these. This check can be done
      // once, here, as the setting will be the same for all translations.
      if (!$node_multilingual->type->entity->getThirdPartySetting('scheduler', 'publish_enable', $this->setting('default_publish_enable'))) {
        throw new SchedulerNodeTypeNotEnabledException(sprintf("Node %d '%s' will not be published because node type '%s' is not enabled for scheduled publishing", $node_multilingual->id(), $node_multilingual->getTitle(), node_get_type_label($node_multilingual)));
      }

      $languages = $node_multilingual->getTranslationLanguages();
      foreach ($languages as $language) {
        // The object returned by getTranslation() behaves the same as a $node.
        $node = $node_multilingual->getTranslation($language->getId());

        // If the current translation does not have a publish on value, or it is
        // later than the date we are processing then move on to the next.
        $publish_on = $node->publish_on->value;
        if (empty($publish_on) || $publish_on > $this->time->getRequestTime()) {
          continue;
        }

        // Check that other modules allow the action on this node.
        if (!$this->isAllowed($node, $action)) {
          continue;
        }

        // $node->setChangedTime($publish_on) will fail badly if an API call has
        // removed the date. Trap this as an exception here and give a
        // meaningful message.
        // @TODO This will now never be thrown due to the empty(publish_on)
        // check above to cater for translations. Remove this exception?
        if (empty($node->publish_on->value)) {
          $field_definitions = $this->entityTypeManager->getFieldDefinitions('node', $node->getType());
          $field = (string) $field_definitions['publish_on']->getLabel();
          throw new SchedulerMissingDateException(sprintf("Node %d '%s' will not be published because field '%s' has no value", $node->id(), $node->getTitle(), $field));
        }

        // Trigger the PRE_PUBLISH event so that modules can react before the
        // node is published.
        $event = new SchedulerEvent($node);
        $this->eventDispatcher->dispatch(SchedulerEvents::PRE_PUBLISH, $event);
        $node = $event->getNode();

        // Update 'changed' timestamp.
        $node->setChangedTime($publish_on);
        $old_creation_date = $node->getCreatedTime();
        $msg_extra = '';
        // If required, set the created date to match published date.
        if ($node->type->entity->getThirdPartySetting('scheduler', 'publish_touch', $this->setting('default_publish_touch')) ||
          ($node->getCreatedTime() > $publish_on && $node->type->entity->getThirdPartySetting('scheduler', 'publish_past_date_created', $this->setting('default_publish_past_date_created')))
        ) {
          $node->setCreatedTime($publish_on);
          $msg_extra = $this->t('The previous creation date was @old_creation_date, now updated to match the publishing date.', [
            '@old_creation_date' => $this->dateFormatter->format($old_creation_date, 'short'),
          ]);
        }

        $create_publishing_revision = $node->type->entity->getThirdPartySetting('scheduler', 'publish_revision', $this->setting('default_publish_revision'));
        if ($create_publishing_revision) {
          $node->setNewRevision();
          // Use a core date format to guarantee a time is included.
          $revision_log_message = rtrim($this->t('Published by Scheduler. The scheduled publishing date was @publish_on.', [
            '@publish_on' => $this->dateFormatter->format($publish_on, 'short'),
          ]) . ' ' . $msg_extra);
          $node->setRevisionLogMessage($revision_log_message)
            ->setRevisionCreationTime($this->time->getRequestTime());
        }
        // Unset publish_on so the node will not get rescheduled by subsequent
        // calls to $node->save().
        $node->publish_on->value = NULL;

        // Invoke all implementations of hook_scheduler_publish_action() to
        // allow other modules to do the "publishing" process instead of
        // Scheduler.
        $hook = 'scheduler_publish_action';
        $processed = FALSE;
        $failed = FALSE;
        foreach ($this->moduleHandler->getImplementations($hook) as $module) {
          $function = $module . '_' . $hook;
          $return = $function($node);
          $processed = $processed || ($return === 1);
          $failed = $failed || ($return === -1);
        }

        // Log the fact that a scheduled publication is about to take place.
        $view_link = $node->toLink($this->t('View node'));
        $node_type = $this->entityTypeManager->getStorage('node_type')->load($node->bundle());
        $node_type_link = $node_type->toLink($this->t('@label settings', ['@label' => $node_type->label()]), 'edit-form');
        $logger_variables = [
          '@type' => $node_type->label(),
          '%title' => $node->getTitle(),
          'link' => $node_type_link->toString() . ' ' . $view_link->toString(),
          '@hook' => 'hook_' . $hook,
        ];

        if ($failed) {
          // At least one hook function returned a failure or exception, so stop
          // processing this node and move on to the next one.
          $this->logger->warning('Publishing failed for %title. Calls to @hook returned a failure code.', $logger_variables);
          continue;
        }
        elseif ($processed) {
          // The node had 'publishing' processed by a module implementing the
          // hook, so no need to do anything more, apart from log this result.
          $this->logger->notice('@type: scheduled processing of %title completed by calls to @hook.', $logger_variables);
        }
        else {
          // None of the above hook calls processed the node and there were no
          // errors detected so set the node to published.
          $this->logger->notice('@type: scheduled publishing of %title.', $logger_variables);
          $node->setPublished();
        }

        // Invoke the event to tell Rules that Scheduler has published the node.
        if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
          _scheduler_rules_integration_dispatch_cron_event($node, 'publish');
        }

        // Trigger the PUBLISH event so that modules can react after the node is
        // published.
        $event = new SchedulerEvent($node);
        $this->eventDispatcher->dispatch(SchedulerEvents::PUBLISH, $event);

        // Use the standard actions system to publish and save the node.
        $node = $event->getNode();
        $action_id = 'node_publish_action';
        if ($this->moduleHandler->moduleExists('workbench_moderation_actions')) {
          // workbench_moderation_actions module uses a custom action instead.
          $action_id = 'state_change__node__published';
        }
        $this->entityTypeManager->getStorage('action')->load($action_id)->getPlugin()->execute($node);

        $result = TRUE;
      }
    }

    return $result;
  }

  /**
   * Unpublish scheduled nodes.
   *
   * @return bool
   *   TRUE if any node has been unpublished, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerMissingDateException
   * @throws \Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException
   */
  public function unpublish() {
    $result = FALSE;
    $action = 'unpublish';

    // Select all nodes of the types that are enabled for scheduled unpublishing
    // and where unpublish_on is less than or equal to the current time.
    $nids = [];
    $scheduler_enabled_types = array_keys(_scheduler_get_scheduler_enabled_node_types($action));
    if (!empty($scheduler_enabled_types)) {
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->exists('unpublish_on')
        ->condition('unpublish_on', $this->time->getRequestTime(), '<=')
        ->condition('type', $scheduler_enabled_types, 'IN')
        ->latestRevision()
        ->sort('unpublish_on')
        ->sort('nid');
      // Disable access checks for this query.
      // @see https://www.drupal.org/node/2700209
      $query->accessCheck(FALSE);
      $nids = $query->execute();
    }

    // Allow other modules to add to the list of nodes to be unpublished.
    $nids = array_unique(array_merge($nids, $this->nidList($action)));

    // Allow other modules to alter the list of nodes to be unpublished.
    $this->moduleHandler->alter('scheduler_nid_list', $nids, $action);

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->loadNodes($nids);
    foreach ($nodes as $node_multilingual) {
      // The API calls could return nodes of types which are not enabled for
      // scheduled unpublishing. Do not process these.
      if (!$node_multilingual->type->entity->getThirdPartySetting('scheduler', 'unpublish_enable', $this->setting('default_unpublish_enable'))) {
        throw new SchedulerNodeTypeNotEnabledException(sprintf("Node %d '%s' will not be unpublished because node type '%s' is not enabled for scheduled unpublishing", $node_multilingual->id(), $node_multilingual->getTitle(), node_get_type_label($node_multilingual)));
      }

      $languages = $node_multilingual->getTranslationLanguages();
      foreach ($languages as $language) {
        // The object returned by getTranslation() behaves the same as a $node.
        $node = $node_multilingual->getTranslation($language->getId());

        // If the current translation does not have an unpublish on value, or it
        // is later than the date we are processing then move on to the next.
        $unpublish_on = $node->unpublish_on->value;
        if (empty($unpublish_on) || $unpublish_on > $this->time->getRequestTime()) {
          continue;
        }

        // Do not process the node if it still has a publish_on time which is in
        // the past, as this implies that scheduled publishing has been blocked
        // by one of the hook functions we provide, and is still being blocked
        // now that the unpublishing time has been reached.
        $publish_on = $node->publish_on->value;
        if (!empty($publish_on) && $publish_on <= $this->time->getRequestTime()) {
          continue;
        }

        // Check that other modules allow the action on this node.
        if (!$this->isAllowed($node, $action)) {
          continue;
        }

        // $node->setChangedTime($unpublish_on) will fail badly if an API call
        // has removed the date. Trap this as an exception here and give a
        // meaningful message.
        // @TODO This will now never be thrown due to the empty(unpublish_on)
        // check above to cater for translations. Remove this exception?
        if (empty($unpublish_on)) {
          $field_definitions = $this->entityTypeManager->getFieldDefinitions('node', $node->getType());
          $field = (string) $field_definitions['unpublish_on']->getLabel();
          throw new SchedulerMissingDateException(sprintf("Node %d '%s' will not be unpublished because field '%s' has no value", $node->id(), $node->getTitle(), $field));
        }

        // Trigger the PRE_UNPUBLISH event so that modules can react before the
        // node is unpublished.
        $event = new SchedulerEvent($node);
        $this->eventDispatcher->dispatch(SchedulerEvents::PRE_UNPUBLISH, $event);
        $node = $event->getNode();

        // Update 'changed' timestamp.
        $node->setChangedTime($unpublish_on);

        $create_unpublishing_revision = $node->type->entity->getThirdPartySetting('scheduler', 'unpublish_revision', $this->setting('default_unpublish_revision'));
        if ($create_unpublishing_revision) {
          $node->setNewRevision();
          // Use a core date format to guarantee a time is included.
          $revision_log_message = $this->t('Unpublished by Scheduler. The scheduled unpublishing date was @unpublish_on.', [
            '@unpublish_on' => $this->dateFormatter->format($unpublish_on, 'short'),
          ]);
          // Create the new revision, setting message and revision timestamp.
          $node->setRevisionLogMessage($revision_log_message)
            ->setRevisionCreationTime($this->time->getRequestTime());
        }
        // Unset unpublish_on so the node will not get rescheduled by subsequent
        // calls to $node->save().
        $node->unpublish_on->value = NULL;

        // Invoke all implementations of hook_scheduler_unpublish_action() to
        // allow other modules to do the "unpublishing" process instead of
        // Scheduler.
        $hook = 'scheduler_unpublish_action';
        $processed = FALSE;
        $failed = FALSE;
        foreach ($this->moduleHandler->getImplementations($hook) as $module) {
          $function = $module . '_' . $hook;
          $return = $function($node);
          $processed = $processed || ($return === 1);
          $failed = $failed || ($return === -1);
        }

        // Set up the log variables.
        $view_link = $node->toLink($this->t('View node'));
        $node_type = $this->entityTypeManager->getStorage('node_type')->load($node->bundle());
        $node_type_link = $node_type->toLink($this->t('@label settings', ['@label' => $node_type->label()]), 'edit-form');
        $logger_variables = [
          '@type' => $node_type->label(),
          '%title' => $node->getTitle(),
          'link' => $node_type_link->toString() . ' ' . $view_link->toString(),
          '@hook' => 'hook_' . $hook,
        ];

        if ($failed) {
          // At least one hook function returned a failure or exception, so stop
          // processing this node and move on to the next one.
          $this->logger->warning('Unpublishing failed for %title. Calls to @hook returned a failure code.', $logger_variables);
          continue;
        }
        elseif ($processed) {
          // The node has 'unpublishing' processed by a module implementing the
          // hook, so no need to do anything more, apart from log this result.
          $this->logger->notice('@type: scheduled processing of %title completed by calls to @hook.', $logger_variables);
        }
        else {
          // None of the above hook calls processed the node and there were no
          // errors detected so set the node to unpublished.
          $this->logger->notice('@type: scheduled unpublishing of %title.', $logger_variables);
          $node->setUnpublished();
        }

        // Invoke event to tell Rules that Scheduler has unpublished this node.
        if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
          _scheduler_rules_integration_dispatch_cron_event($node, 'unpublish');
        }

        // Trigger the UNPUBLISH event so that modules can react after the node
        // is unpublished.
        $event = new SchedulerEvent($node);
        $this->eventDispatcher->dispatch(SchedulerEvents::UNPUBLISH, $event);

        // Use the standard actions system to unpublish and save the node.
        $node = $event->getNode();
        $action_id = 'node_unpublish_action';
        if ($this->moduleHandler->moduleExists('workbench_moderation_actions')) {
          // workbench_moderation_actions module uses a custom action instead.
          $action_id = 'state_change__node__archived';
        }
        $this->entityTypeManager->getStorage('action')->load($action_id)->getPlugin()->execute($node);

        $result = TRUE;
      }
    }

    return $result;
  }

  /**
   * Checks whether a scheduled action on a node is allowed.
   *
   * This provides a way for other modules to prevent scheduled publishing or
   * unpublishing, by implementing hook_scheduler_allow_publishing() or
   * hook_scheduler_allow_unpublishing().
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node on which the action is to be performed.
   * @param string $action
   *   The action that needs to be checked. Can be 'publish' or 'unpublish'.
   *
   * @return bool
   *   TRUE if the action is allowed, FALSE if not.
   *
   * @see hook_scheduler_allow_publishing()
   * @see hook_scheduler_allow_unpublishing()
   */
  public function isAllowed(NodeInterface $node, $action) {
    // Default to TRUE.
    $result = TRUE;
    // Check that other modules allow the action.
    $hook = 'scheduler_allow_' . $action . 'ing';
    foreach ($this->moduleHandler->getImplementations($hook) as $module) {
      $function = $module . '_' . $hook;
      $result &= $function($node);
    }

    return $result;
  }

  /**
   * Gather node IDs for all nodes that need to be $action'ed.
   *
   * Modules can implement hook_scheduler_nid_list($action) and return an array
   * of node ids which will be added to the existing list.
   *
   * @param string $action
   *   The action being performed, either "publish" or "unpublish".
   *
   * @return array
   *   An array of node ids.
   */
  public function nidList($action) {
    $nids = [];

    foreach ($this->moduleHandler->getImplementations('scheduler_nid_list') as $module) {
      $function = $module . '_scheduler_nid_list';
      $nids = array_merge($nids, $function($action));
    }

    return $nids;
  }

  /**
   * Run the lightweight cron.
   *
   * The Scheduler part of the processing performed here is the same as in the
   * normal Drupal cron run. The difference is that only scheduler_cron() is
   * executed, no other modules hook_cron() functions are called.
   *
   * This function is called from the external crontab job via url
   * /scheduler/cron/{access key} or it can be run interactively from the
   * Scheduler configuration page at /admin/config/content/scheduler/cron.
   * It is also executed when running Scheduler Cron via drush.
   *
   * @param array $options
   *   Options passed from drush command or admin form.
   */
  public function runLightweightCron(array $options = []) {
    // When calling via drush the log messages can be avoided by using --nolog.
    $log = $this->setting('log') && empty($options['nolog']);
    if ($log) {
      if (array_key_exists('nolog', $options)) {
        $trigger = 'drush command';
      }
      elseif (array_key_exists('admin_form', $options)) {
        $trigger = 'admin user form';
      }
      else {
        $trigger = 'url';
      }
      $this->logger->notice('Lightweight cron run activated by @trigger.', ['@trigger' => $trigger]);
    }
    scheduler_cron();
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      if (isset($handlers[0]) && $handlers[0] == 'default output handler') {
        ob_clean();
      }
    }
    if ($log) {
      $link = Link::fromTextAndUrl($this->t('settings'), Url::fromRoute('scheduler.cron_form'));
      $this->logger->notice('Lightweight cron run completed.', ['link' => $link->toString()]);
    }
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The value of the configuration item requested.
   */
  protected function setting($key) {
    return $this->configFactory->get('scheduler.settings')->get($key);
  }

  /**
   * Helper method to load latest revision of each node.
   *
   * @param array $nids
   *   Array of node ids.
   *
   * @return array
   *   Array of loaded nodes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function loadNodes(array $nids) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $nodes = [];

    // Load the latest revision for each node.
    foreach ($nids as $nid) {
      $node = $node_storage->load($nid);
      $revision_ids = $node_storage->revisionIds($node);
      $vid = end($revision_ids);
      $nodes[] = $node_storage->loadRevision($vid);
    }

    return $nodes;
  }

}
