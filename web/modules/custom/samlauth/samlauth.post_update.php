<?php

/**
 * @file
 * Post update functions for the samlauth module.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\views\Entity\View;

/**
 * Imports new view for authmap entries.
 */
function samlauth_post_update_add_view_samlauth_map() {
  if (\Drupal::moduleHandler()->moduleExists('views') && !View::load('samlauth_map')) {
    $module_path = \Drupal::moduleHandler()->getModule('samlauth')->getPath();
    $file_storage = new FileStorage($module_path . '/' . InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
    $view = \Drupal::entityTypeManager()->getStorage('view')->create($file_storage->read('views.view.samlauth_map'));
    $view->save();
  }
}
