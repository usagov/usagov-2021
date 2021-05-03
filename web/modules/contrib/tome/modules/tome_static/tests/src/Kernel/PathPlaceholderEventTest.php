<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Tests path placeholder event class.
 *
 * @coversDefaultClass \Drupal\tome_static\Event\PathPlaceholderEvent
 * @group tome
 */
class PathPlaceholderEventTest extends TomeStaticEventTestBase {

  /**
   * {@inheritdoc}
   */
  protected $eventName = TomeStaticEvents::PATH_PLACEHOLDER;

  /**
   * @covers \Drupal\tome_static\Event\PathPlaceholderEvent::setPath
   * @covers \Drupal\tome_static\Event\PathPlaceholderEvent::getPath
   */
  public function testSetPath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'setPath']);
    $event = $this->pathPlaceholder();

    $this->assertEquals('/my-path-replaced', $event->getPath());
  }

  /**
   * @covers \Drupal\tome_static\Event\PathPlaceholderEvent::setInvalid
   * @covers \Drupal\tome_static\Event\PathPlaceholderEvent::isInvalid
   */
  public function testIsInvalid() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'isInvalid']);
    $event = $this->pathPlaceholder();

    $this->assertEquals(TRUE, $event->isInvalid());
  }

  /**
   * Triggers the path placeholder event and returns the updated event.
   *
   * @param string $path
   *   (optional) The HTML path.
   *
   * @return \Drupal\tome_static\Event\PathPlaceholderEvent
   *   The path placeholder event after it is triggered.
   */
  protected function pathPlaceholder($path = '/my-path/{placeholder}') {
    $event = new PathPlaceholderEvent($path);
    $this->eventDispatcher->dispatch($this->eventName, $event);
    return $event;
  }

  /**
   * Emulates the setPath() listener.
   *
   * @param \Drupal\tome_static\Event\PathPlaceholderEvent $event
   *   The path placeholder event.
   */
  public function setPath(PathPlaceholderEvent $event) {
    $event->setPath('/my-path-replaced');
  }

  /**
   * Emulates the isInvalid() listener.
   *
   * @param \Drupal\tome_static\Event\PathPlaceholderEvent $event
   *   The path placeholder event.
   */
  public function isinvalid(PathPlaceholderEvent $event) {
    $event->setInvalid();
  }

}
