<?php

namespace Drupal\webform\Plugin\DevelGenerate;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devel_generate\DevelGenerateBase;

/**
 * Provides a WebformSubmissionDevelGenerate deprecated plugin.
 *
 * Use deprecated generator because the devel_generate.module changed the
 * DevelGenerateBaseInterface.
 *
 * @see https://www.drupal.org/project/webform/issues/3155654
 * @see https://gitlab.com/drupalspoons/devel/-/issues/324
 */
class WebformSubmissionDevelGenerateDeprecated extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  use WebformSubmissionDevelGenerateTrait;

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams($args) {
    return $this->_validateDrushParams($args);
  }

}
