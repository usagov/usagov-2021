<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Tests modify destination event class.
 *
 * @coversDefaultClass \Drupal\tome_static\Event\ModifyDestinationEvent
 * @group tome
 */
class ModifyDestinationEventTest extends TomeStaticEventTestBase {

  /**
   * {@inheritdoc}
   */
  protected $eventName = TomeStaticEvents::MODIFY_DESTINATION;

  /**
   * @covers \Drupal\tome_static\Event\ModifyDestinationEvent::setDestination
   * @covers \Drupal\tome_static\Event\ModifyDestinationEvent::getDestination
   */
  public function testSetDestination() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'setDestination']);
    $destination = $this->ModifyDestination();

    $this->assertEquals('/my-new-destination', $destination);
  }

  /**
   * Triggers the modify destination event and returns the final destination.
   *
   * @param string $destination
   *   (optional) The initial destination.
   *
   * @return string
   *   The modified destination once the modify destination event is triggered.
   */
  protected function modifyDestination($destination = '/my-destination') {
    $event = new ModifyDestinationEvent($destination);
    $this->eventDispatcher->dispatch($this->eventName, $event);
    return $event->getDestination();
  }

  /**
   * Emulates the setDestination() listener.
   *
   * @param \Drupal\tome_static\Event\ModifyDestinationEvent $event
   *   The modify destination event.
   */
  public function setDestination(ModifyDestinationEvent $event) {
    $event->setDestination('/my-new-destination');
  }

}
