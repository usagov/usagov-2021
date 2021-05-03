<?php

namespace Drupal\Tests\scheduler\Traits;

/**
 * PHPUnit forward compatibility for assertions not backported to core 8.7.
 *
 * @todo Remove this trait when core 8.8 is the lowest supported version.
 * @see https://www.drupal.org/project/scheduler/issues/3136744
 */

if (!method_exists('Drupal\Tests\BrowserTestBase', 'assertStringContainsString')) {
  /**
   * Forwards compatibility trait with the required missig methods.
   */
  trait PhpunitCompatibilityCore87Trait {

    /**
     * Forward compatibility for assertStringContainsString.
     */
    public static function assertStringContainsString($needle, $haystack, $message = '') {
      static::assertContains($needle, $haystack, $message);
    }

    /**
     * Forward compatibility for assertStringNotContainsString.
     */
    public static function assertStringNotContainsString($needle, $haystack, $message = '') {
      static::assertNotContains($needle, $haystack, $message);
    }

  }
}
else {
  /**
   * Empty trait as the methods already exist.
   */
  trait PhpunitCompatibilityCore87Trait {
  }
}
