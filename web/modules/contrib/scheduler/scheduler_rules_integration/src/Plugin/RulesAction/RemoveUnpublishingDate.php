<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides a 'Remove date for scheduled unpublishing' action.
 *
 * @RulesAction(
 *   id = "scheduler_remove_unpublishing_date_action",
 *   label = @Translation("Remove date for scheduled unpublishing"),
 *   category = @Translation("Scheduler"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node from which to remove the scheduled unpublishing date"),
 *     ),
 *   }
 * )
 */
class RemoveUnpublishingDate extends RulesActionBase {

  /**
   * Remove the unpublish_on date from the node.
   */
  public function doExecute() {
    $node = $this->getContextValue('node');
    $config = \Drupal::config('scheduler.settings');
    if ($node->type->entity->getThirdPartySetting('scheduler', 'unpublish_enable', $config->get('default_unpublish_enable'))) {
      $node->set('unpublish_on', NULL);
      scheduler_node_presave($node);
      scheduler_node_update($node);
    }
    else {
      // The action cannot be executed because the content type is not enabled
      // for scheduled unpublishing.
      $action_label = $this->summary();
      // @todo Can we get the condition description from the actual condition
      // object instead of hard-coding it here?
      $condition = $this->t('Node type is enabled for scheduled unpublishing');
      $type_name = node_get_type_label($node);
      $url = new Url('entity.node_type.edit_form', ['node_type' => $node->getType()]);
      $arguments = [
        '%type' => $type_name,
        '%action_label' => $action_label,
        '%condition' => $condition,
        '@url' => $url->toString(),
        'link' => Link::fromTextAndUrl($this->t('@type settings', ['@type' => $type_name]), $url)->toString(),
      ];

      \Drupal::logger('scheduler')->warning('Action "%action_label" is not valid because scheduled unpublishing is not enabled for %type content. Add the condition "%condition" to your Reaction Rule, or enable scheduled unpublishing via the %type settings.',
        $arguments);

      \Drupal::messenger()->addMessage($this->t('Action "%action_label" is not valid because scheduled unpublishing is not enabled for %type content. Add the condition "%condition" to your Reaction Rule, or enable scheduled unpublishing via the <a href="@url">%type</a> settings.',
        $arguments), 'warning', FALSE);
    }
  }

}
