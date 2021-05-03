<?php

namespace Drupal\Tests\content_lock\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\content_lock\Functional\ContentLockTestTrait;

/**
 * Class ContentLockJavascriptTestBase.
 */
abstract class ContentLockJavascriptTestBase extends WebDriverTestBase {

  use ContentLockTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'content_lock',
  ];

}
