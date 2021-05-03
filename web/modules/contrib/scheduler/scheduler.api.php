<?php

/**
 * @file
 * API documentation for the Scheduler module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Hook function to add node ids to the list being processed.
 *
 * This hook allows modules to add more node ids into the list being processed
 * in the current cron run. It is invoked during cron runs only. This function
 * is retained for backwards compatibility but is superceded by the more
 * flexible hook_scheduler_nid_list_alter().
 *
 * @param string $action
 *   The action being done to the node - 'publish' or 'unpublish'.
 *
 * @return array
 *   Array of node ids to add to the existing list of nodes to be processed.
 */
function hook_scheduler_nid_list($action) {
  $nids = [];
  // Do some processing to add new node ids into $nids.
  return $nids;
}

/**
 * Hook function to manipulate the list of nodes being processed.
 *
 * This hook allows modules to add or remove node ids from the list being
 * processed in the current cron run. It is invoked during cron runs only. It
 * can do everything that hook_scheduler_nid_list() does, plus more.
 *
 * @param array $nids
 *   An array of node ids being processed.
 * @param string $action
 *   The action being done to the node - 'publish' or 'unpublish'.
 *
 * @return array
 *   The full array of node ids to process, adjusted as required.
 */
function hook_scheduler_nid_list_alter(array &$nids, $action) {
  // Do some processing to add or remove node ids.
  return $nids;
}

/**
 * Hook function to deny or allow a node to be published.
 *
 * This hook gives modules the ability to prevent publication of a node at the
 * scheduled time. The node may be scheduled, and an attempt to publish it will
 * be made during the first cron run after the publishing time. If this hook
 * returns FALSE the node will not be published. Attempts at publishing will
 * continue on each subsequent cron run until this hook returns TRUE.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The scheduled node that is about to be published.
 *
 * @return bool
 *   TRUE if the node can be published, FALSE if it should not be published.
 */
function hook_scheduler_allow_publishing(NodeInterface $node) {
  // If there is no 'approved' field do nothing to change the result.
  if (!isset($node->field_approved)) {
    $allowed = TRUE;
  }
  else {
    // Prevent publication of nodes that do not have the 'Approved for
    // publication by the CEO' checkbox ticked.
    $allowed = !empty($node->field_approved->value);

    // If publication is denied then inform the user why. This message will be
    // displayed during node edit and save.
    if (!$allowed) {
      \Drupal::messenger()->addMessage(t('The content will only be published after approval by the CEO.'), 'status', FALSE);
    }
  }

  return $allowed;
}

/**
 * Hook function to deny or allow a node to be unpublished.
 *
 * This hook gives modules the ability to prevent unpblication of a node at the
 * scheduled time. The node may be scheduled, and an attempt to unpublish it
 * will be made during the first cron run after the unpublishing time. If this
 * hook returns FALSE the node will not be unpublished. Attempts at unpublishing
 * will continue on each subsequent cron run until this hook returns TRUE.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The scheduled node that is about to be unpublished.
 *
 * @return bool
 *   TRUE if the node can be unpublished, FALSE if it should not be unpublished.
 */
function hook_scheduler_allow_unpublishing(NodeInterface $node) {
  $allowed = TRUE;

  // Prevent unpublication of competition entries if not all prizes have been
  // claimed.
  if ($node->getType() == 'competition' && $items = $node->field_competition_prizes->getValue()) {
    $allowed = (bool) count($items);

    // If unpublication is denied then inform the user why. This message will be
    // displayed during node edit and save.
    if (!$allowed) {
      \Drupal::messenger()->addMessage(t('The competition will only be unpublished after all prizes have been claimed by the winners.'));
    }
  }

  return $allowed;
}

/**
 * Hook function to hide the Publish On field.
 *
 * This hook is called from scheduler_form_node_form_alter(). It gives modules
 * the ability to hide the scheduler publish_on input field on the node edit
 * form. Note that it does not give the ability to force the field to be
 * displayed, as that could override a more significant setting. It can only be
 * used to hide the field.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 *
 * @param array $form
 *   An associative array containing the structure of the form, as used in
 *   hook_form_alter().
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form, as used in hook_form_alter().
 * @param \Drupal\node\NodeInterface $node
 *   The $node object of the node being editted.
 *
 * @see https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @return bool
 *   TRUE to hide the publish_on field.
 *   FALSE or NULL to leave the setting unchanged.
 */
function hook_scheduler_hide_publish_on_field(array $form, FormStateInterface $form_state, NodeInterface $node) {
  return FALSE;
}

/**
 * Hook function to hide the Unpublish On field.
 *
 * This hook is called from scheduler_form_node_form_alter(). It gives modules
 * the ability to hide the scheduler unpublish_on input field on the node edit
 * form. Note that it does not give the ability to force the field to be
 * displayed, as that could override a more significant setting. It can only be
 * used to hide the field.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 *
 * @param array $form
 *   An associative array containing the structure of the form, as used in
 *   hook_form_alter().
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form, as used in hook_form_alter().
 * @param \Drupal\node\NodeInterface $node
 *   The $node object of the node being editted.
 *
 * @see https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @return bool
 *   TRUE to hide the unpublish_on field.
 *   FALSE or NULL to leave the setting unchanged.
 */
function hook_scheduler_hide_unpublish_on_field(array $form, FormStateInterface $form_state, NodeInterface $node) {
  return FALSE;
}

/**
 * Hook function to process the publish action for a node.
 *
 * This hook is called from schedulerManger::publish() and allows oher modules
 * to process the publish action on a node during a cron run. The other module
 * may require different functionality to be executed instead of the default
 * publish process. If none of the invoked hook functions return a TRUE value
 * then Scheduler will process the node using the default publish action, just
 * as if no other hooks had been called.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The $node object of the node being published.
 *
 * @see https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @return int
 *   1 if this function has published the node or performed other such action
 *     meaning that Scheduler should NOT process the default publish action.
 *   0 if nothing has been done and Scheduler should process the default publish
 *     action just as if this hook function did not exist.
 *   -1 if an error has occurred and Scheduler should abandon processing this
 *     node with no further action and move on to the next one.
 */
function hook_scheduler_publish_action(NodeInterface $node) {
  return 0;
}

/**
 * Hook function to process the unpublish action for a node.
 *
 * This hook is called from schedulerManger::unpublish() and allows oher modules
 * to process the unpublish action on a node during a cron run. The other module
 * may require different functionality to be executed instead of the default
 * unpublish process. If none of the invoked hook functions return a TRUE value
 * then Scheduler will process the node using the default unpublish action, just
 * as if no other hooks had been called.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The $node object of the node being unpublished.
 *
 * @see https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @return int
 *   1 if this function has published the node or performed other such action
 *     meaning that Scheduler should NOT process the default publish action.
 *   0 if nothing has been done and Scheduler should process the default publish
 *     action just as if this hook function did not exist.
 *   -1 if an error has occurred and Scheduler should abandon processing this
 *     node with no further action and move on to the next one.
 */
function hook_scheduler_unpublish_action(NodeInterface $node) {
  return 0;
}

/**
 * @} End of "addtogroup hooks".
 */
