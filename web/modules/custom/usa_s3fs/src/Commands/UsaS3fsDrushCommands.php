<?php

namespace Drupal\usa_s3fs\Commands;


use Drush\Commands\DrushCommands;
use Drupal\Core\Site\Settings;

/**
 * A drush command file.
 *
 * @package Drupal\drush9_custom_commands\Commands
 */
class UsaS3fsDrushCommands extends DrushCommands {
  /**
   * Drush command that displays the given text.
   *
   * @param string $text
   *   Argument with message to be displayed.
   * @command drush9_custom_commands:message
   * @aliases us3fs us3fs-msg
   * @option uppercase
   *   Uppercase the message.
   * @option reverse
   *   Reverse the message.
   * @usage drush9_custom_commands:message --uppercase --reverse drupal8
   */
  public function message($text = 'Hello world!', $options = ['uppercase' => FALSE, 'reverse' => FALSE]) {
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('s3fs')) {
      echo 's3fsmoduleExists' . PHP_EOL;
      $config = \Drupal::config('s3fs.settings')->get();
      var_dump($config);
      $scheme = 'public';
    }

    if ($options['uppercase']) {
      $text = strtoupper($text);
    }
    if ($options['reverse']) {
      $text = strrev($text);
    }
    $this->output()->writeln($text);
  }
  public function validateConfigValidate() {
    $config = \Drupal::config('s3fs.settings')->get();
    if ($errors = \Drupal::service('s3fs')->validate($config)) {
      $errorText = $this->t("Unable to validate your s3fs configuration settings. Please configure S3 File System from the admin/config/media/s3fs page or settings.php and try again.");
      foreach ($errors as $error) {
        $errorText .= "<br>\n" . $error;
      }
    }
  }
}
