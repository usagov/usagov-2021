<?php

/**
 * @file
 * Contains post update hooks.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\workbench_email\TemplateInterface;
use Drupal\workbench_email\Update\UpdateHelper;

/**
 * Updates config entities to use the new recipient plugins.
 */
function workbench_email_post_update_move_to_recipient_plugins(&$sandbox = NULL) {
  if (class_exists(ConfigEntityUpdater::class)) {
    \Drupal::classResolver(ConfigEntityUpdater::class)
      ->update($sandbox, 'workbench_email_template', [UpdateHelper::class, 'updateToRecipientPlugin']);
    return;
  }
  // Drupal core < 8.6, no config entity updater.
  \Drupal::classResolver()->getInstanceFromDefinition(UpdateHelper::class)->legacyUpdateToRecipientPlugin($sandbox);
}

/**
 * Clear caches due to addition of replyTo field.
 */
function workbench_email_post_update_add_reply_to() {
  // Empty post-update hook.
}
