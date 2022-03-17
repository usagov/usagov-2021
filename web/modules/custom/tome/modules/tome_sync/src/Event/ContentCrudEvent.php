<?php

namespace Drupal\tome_sync\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a content event for listeners.
 */
class ContentCrudEvent extends Event {

  /**
   * Content object.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $content;

  /**
   * Constructs a CollectPathsEvent object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $content
   *   Content object.
   */
  public function __construct(ContentEntityInterface $content) {
    $this->content = $content;
  }

  /**
   * Gets the content object.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content object that caused the event to fire.
   */
  public function getContent() {
    return $this->content;
  }

}
