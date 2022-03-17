<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\tome_static\StackMiddleware\ResettablePageCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepares for a new request when using page cache.
 *
 * @internal
 */
class PageCacheRequestPrepareSubscriber implements EventSubscriberInterface {

  /**
   * The resettable page cache.
   *
   * @var \Drupal\tome_static\StackMiddleware\ResettablePageCache
   */
  protected $pageCache;

  /**
   * Constructs the EntityPathSubscriber object.
   *
   * @param \Drupal\tome_static\StackMiddleware\ResettablePageCache $page_cache
   *   The resettable page cache.
   */
  public function __construct(ResettablePageCache $page_cache) {
    $this->pageCache = $page_cache;
  }

  /**
   * Reacts to a collect paths event.
   */
  public function prepareForRequest() {
    $this->pageCache->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::REQUEST_PREPARE][] = ['prepareForRequest'];
    return $events;
  }

}
