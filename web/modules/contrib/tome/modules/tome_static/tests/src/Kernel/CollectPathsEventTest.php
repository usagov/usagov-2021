<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Tests collect paths event class.
 *
 * @coversDefaultClass \Drupal\tome_static\Event\CollectPathsEvent
 * @group tome
 */
class CollectPathsEventTest extends TomeStaticEventTestBase {

  /**
   * {@inheritdoc}
   */
  protected $eventName = TomeStaticEvents::COLLECT_PATHS;

  /**
   * @covers \Drupal\tome_static\Event\CollectPathsEvent::addPath
   */
  public function testAddPath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'addPath']);
    $paths = $this->collectPaths();

    $this->assertEquals(1, count($paths));
    $this->assertUnsortedEquals($paths, ['/my-path']);
  }

  /**
   * @covers \Drupal\tome_static\Event\CollectPathsEvent::addPaths
   */
  public function testAddPaths() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'addPaths']);
    $paths = $this->collectPaths();

    $this->assertEquals(2, count($paths));
    $this->assertUnsortedEquals($paths, ['/my-path', '/my-second-path']);
  }

  /**
   * @covers \Drupal\tome_static\Event\CollectPathsEvent::replacePath
   */
  public function testReplacePath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'replacePath']);
    $paths = $this->collectPaths(['/my-path', '/another-path']);

    $this->assertEquals(2, count($paths));
    $this->assertUnsortedEquals($paths, ['/my-new-path', '/another-path']);
  }

  /**
   * @covers \Drupal\tome_static\Event\CollectPathsEvent::replacePaths
   */
  public function testReplacePaths() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'replacePaths']);
    $paths = $this->collectPaths(['/my-path', '/another-path']);

    $this->assertEquals(1, count($paths));
    $this->assertUnsortedEquals($paths, ['/my-new-path']);
  }

  /**
   * @covers \Drupal\tome_static\Event\CollectPathsEvent::deletePath
   */
  public function testDeletePath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'deletePath']);
    $paths = $this->collectPaths(['/my-path', '/another-path']);

    $this->assertEquals(1, count($paths));
    $this->assertUnsortedEquals($paths, ['/my-path']);
  }

  /**
   * Triggers the collect paths event and returns the collected paths.
   *
   * @param array $paths
   *   The initial paths array.
   *
   * @return array
   *   The processed paths array once the collect paths event is triggered.
   */
  protected function collectPaths(array $paths = []) {
    $event = new CollectPathsEvent($paths);
    $this->eventDispatcher->dispatch($this->eventName, $event);
    return $event->getPaths();
  }

  /**
   * Emulates the addPath() listener.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function addPath(CollectPathsEvent $event) {
    $event->addPath('/my-path');
  }

  /**
   * Emulates the addPaths() listener.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function addPaths(CollectPathsEvent $event) {
    $event->addPaths(['/my-path', '/my-second-path']);
  }

  /**
   * Emulates the replacePath() listener.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function replacePath(CollectPathsEvent $event) {
    $event->replacePath('/my-path', '/my-new-path');
  }

  /**
   * Emulates the replacePaths() listener.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function replacePaths(CollectPathsEvent $event) {
    $event->replacePaths(['/my-new-path']);
  }

  /**
   * Emulates the deletePath() listener.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function deletePath(CollectPathsEvent $event) {
    $event->deletePath('/another-path');
  }

}
