<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Helper test class with some added functions for Tome static event testing.
 */
abstract class TomeStaticEventTestBase extends KernelTestBase {

  /**
   * The Tome event name to react to.
   *
   * @var string
   */
  protected $eventName;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['tome_static'];

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->eventDispatcher = \Drupal::service('event_dispatcher');

    $listeners = $this->eventDispatcher->getListeners($this->eventName);

    foreach ($listeners as $listener) {
      $this->eventDispatcher->removeListener($this->eventName, $listener);
    }
  }

  /**
   * Asserts that two arrays are equal, ignoring sort order.
   *
   * @param array $array1
   *   The first array to compare.
   * @param array $array2
   *   The second array to compare.
   */
  protected function assertUnsortedEquals(array $array1, array $array2) {
    sort($array1);
    sort($array2);
    $this->assertEquals($array1, $array2);
  }

}
