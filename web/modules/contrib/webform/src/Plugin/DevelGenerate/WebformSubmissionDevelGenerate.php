<?php

namespace Drupal\webform\Plugin\DevelGenerate;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devel_generate\DevelGenerateBase;

/**
 * Provides a WebformSubmissionDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "webform_submission",
 *   label = @Translation("Webform submissions"),
 *   description = @Translation("Generate a given number of webform submissions. Optionally delete current submissions."),
 *   url = "webform",
 *   permission = "administer webform",
 *   settings = {
 *     "num" = 50,
 *     "kill" = FALSE,
 *     "entity-type" = NULL,
 *     "entity-id" = NULL,
 *   }
 * )
 */
class WebformSubmissionDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  use WebformSubmissionDevelGenerateTrait;

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams($args) {
    return $this->_validateDrushParams($args);
  }

}
