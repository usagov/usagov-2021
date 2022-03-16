<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\tome_static\Event\FileSavedEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Tests file saved event class.
 *
 * @coversDefaultClass \Drupal\tome_static\Event\FileSavedEvent
 * @group tome
 */
class FileSavedEventTest extends TomeStaticEventTestBase {

  /**
   * {@inheritdoc}
   */
  protected $eventName = TomeStaticEvents::FILE_SAVED;

  /**
   * @covers \Drupal\tome_static\Event\FileSavedEvent::getPath
   */
  public function testGetPath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'getPath']);
    $event = $this->fileSaved();

    $this->assertEquals('/my-file-path', $event->getPath());
  }

  /**
   * Triggers the path placeholder event and returns the updated event.
   *
   * @param string $path
   *   (optional) The HTML path.
   *
   * @return \Drupal\tome_static\Event\FileSavedEvent
   *   The file saved event after it is triggered.
   */
  protected function fileSaved($path = '/my-file-path') {
    $event = new FileSavedEvent($path);
    $this->eventDispatcher->dispatch($this->eventName, $event);
    return $event;
  }

  /**
   * Emulates the setPath() listener.
   *
   * @param \Drupal\tome_static\Event\FileSavedEvent $event
   *   The file saved event.
   */
  public function getPath(FileSavedEvent $event) {
    $event->getPath();
  }

}
