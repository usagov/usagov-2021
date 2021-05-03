<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides a 'Set date for scheduled publishing' action.
 *
 * @RulesAction(
 *   id = "scheduler_set_publishing_date_action",
 *   label = @Translation("Set date for scheduled publishing"),
 *   category = @Translation("Scheduler"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node for scheduling"),
 *       description = @Translation("The node which is to have a scheduled publishing date set"),
 *     ),
 *     "date" = @ContextDefinition("timestamp",
 *       label = @Translation("The date for publishing"),
 *       description = @Translation("The date when Scheduler will publish the node"),
 *     )
 *   }
 * )
 */
class SetPublishingDate extends RulesActionBase {

  /**
   * Set the publish_on date for the node.
   */
  public function doExecute() {
    $node = $this->getContextValue('node');
    $date = $this->getContextValue('date');
    $config = \Drupal::config('scheduler.settings');
    if ($node->type->entity->getThirdPartySetting('scheduler', 'publish_enable', $config->get('default_publish_enable'))) {
      $node->set('publish_on', $date);
      // When this action is invoked and it operates on the node being editted
      // then hook_node_presave() and hook_node_update() will be executed
      // automatically. But if this action is being used to schedule a different
      // node then we need to call the functions directly here.
      scheduler_node_presave($node);
      scheduler_node_update($node);
    }
    else {
      // The action cannot be executed because the content type is not enabled
      // for scheduled publishing.
      $action_label = $this->summary();
      // @todo Can we get the condition description from the actual condition
      // object instead of hard-coding it here?
      $condition = $this->t('Node type is enabled for scheduled publishing');
      $type_name = node_get_type_label($node);
      $url = new Url('entity.node_type.edit_form', ['node_type' => $node->getType()]);
      $arguments = [
        '%type' => $type_name,
        '%action_label' => $action_label,
        '%condition' => $condition,
        '@url' => $url->toString(),
        'link' => Link::fromTextAndUrl($this->t('@type settings', ['@type' => $type_name]), $url)->toString(),
      ];

      \Drupal::logger('scheduler')->warning('Action "%action_label" is not valid because scheduled publishing is not enabled for %type content. Add the condition "%condition" to your Reaction Rule, or enable scheduled publishing via the %type settings.',
        $arguments);

      \Drupal::messenger()->addMessage($this->t('Action "%action_label" is not valid because scheduled publishing is not enabled for %type content. Add the condition "%condition" to your Reaction Rule, or enable scheduled publishing via the <a href="@url">%type</a> settings.',
        $arguments), 'warning', FALSE);

    }
  }

}
