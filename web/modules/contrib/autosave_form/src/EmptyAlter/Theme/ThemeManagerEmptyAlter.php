<?php

namespace Drupal\autosave_form\EmptyAlter\Theme;

use Drupal\Core\Theme\ThemeManager;

/**
 * Class that has an empty implementation of the hook-alter method.
 */
class ThemeManagerEmptyAlter extends ThemeManager {

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function alter($type, &$data, &$context1 = NULL, &$context2 = NULL) {}

}
