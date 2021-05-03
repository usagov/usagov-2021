<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;

/**
 * Tests modify HTML event class.
 *
 * @coversDefaultClass \Drupal\tome_static\Event\ModifyHtmlEvent
 * @group tome
 */
class ModifyHtmlEventTest extends TomeStaticEventTestBase {

  /**
   * {@inheritdoc}
   */
  protected $eventName = TomeStaticEvents::MODIFY_HTML;

  /**
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::getPath
   */
  public function testGetPath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'addInvokePath']);
    $event = $this->modifyHtml();

    $this->assertEquals('/my-path', $event->getPath());
  }

  /**
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::setHtml
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::getHtml
   */
  public function testSetHtml() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'setHtml']);
    $event = $this->modifyHtml();

    $this->assertEquals('NEW-HTML', $event->getHtml());
  }

  /**
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::addInvokePath
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::getInvokePaths
   */
  public function testAddInvokePath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'addInvokePath']);
    $event = $this->modifyHtml();

    $this->assertUnsortedEquals(['/my-new-path', '/my-new-path-again'], $event->getInvokePaths());
  }

  /**
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::addExcludePath
   * @covers \Drupal\tome_static\Event\ModifyHtmlEvent::getExcludePaths
   */
  public function testAddExcludePath() {
    $this->eventDispatcher->addListener($this->eventName, [$this, 'addExcludePath']);
    $event = $this->modifyHtml();

    $this->assertUnsortedEquals(['/my-new-path', '/my-new-path-again'], $event->getExcludePaths());
  }

  /**
   * Triggers the modify HTML event and returns the updated event.
   *
   * @param string $html
   *   (optional) The HTML content.
   * @param string $path
   *   (optional) The HTML path.
   *
   * @return \Drupal\tome_static\Event\ModifyHtmlEvent
   *   The modified HTML event after it is triggered.
   */
  protected function modifyHtml($html = 'HTML', $path = '/my-path') {
    $event = new ModifyHtmlEvent($html, $path);
    $this->eventDispatcher->dispatch($this->eventName, $event);
    return $event;
  }

  /**
   * Emulates the setHtml() listener.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The modify HTML event.
   */
  public function setHtml(ModifyHtmlEvent $event) {
    $event->setHtml('NEW-HTML');
  }

  /**
   * Emulates the addInvokePath() listener.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The modify HTML event.
   */
  public function addInvokePath(ModifyHtmlEvent $event) {
    $event->addInvokePath('/my-new-path');
    $event->addInvokePath('/my-new-path-again');
  }

  /**
   * Emulates the addExcludePath() listener.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The modify HTML event.
   */
  public function addExcludePath(ModifyHtmlEvent $event) {
    $event->addExcludePath('/my-new-path');
    $event->addExcludePath('/my-new-path-again');
  }

}
