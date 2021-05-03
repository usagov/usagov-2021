<?php

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class for content_lock tests.
 */
abstract class ContentLockTestBase extends BrowserTestBase {

  use ContentLockTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'content_lock',
  ];

}
