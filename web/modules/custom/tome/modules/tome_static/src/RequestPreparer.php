<?php

namespace Drupal\tome_static;

use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Utility class to prepare Drupal for new requests.
 *
 * Hopefully, in the future, this will not be necessary, which is why it isn't
 * used by the static generator directly. This is a hotfix for a core problem.
 *
 * @see \Drupal\tome_static\StaticGeneratorInterface::requestPath
 */
class RequestPreparer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The decorated context repository.
   *
   * @var \Drupal\tome_static\LazyResettableContextRepository
   */
  protected $contextRepository;

  /**
   * The decorated path matcher.
   *
   * @var \Drupal\tome_static\ResettablePathMatcher
   */
  protected $pathMatcher;

  /**
   * The menu active trail cache collector.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a RequestPreparer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tome_static\LazyResettableContextRepository $context_repository
   *   The decorated context repository.
   * @param \Drupal\tome_static\ResettablePathMatcher $path_matcher
   *   The decorated path matcher.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The menu active trail cache collector.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LazyResettableContextRepository $context_repository, ResettablePathMatcher $path_matcher, MenuActiveTrailInterface $menu_active_trail, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contextRepository = $context_repository;
    $this->pathMatcher = $path_matcher;
    $this->menuActiveTrail = $menu_active_trail;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Prepares Drupal for a new request.
   */
  public function prepareForRequest() {
    // Some access control handlers, like block's, determine entity access
    // based on the route (via conditions, in this case).
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      $this->entityTypeManager->getAccessControlHandler($definition->id())->resetCache();
    }
    // Some contrib modules, like metatag, statically cache route data.
    drupal_static_reset();
    // The context repository caches all context values in memory.
    $this->contextRepository->resetCache();
    // The front page route is statically cached.
    $this->pathMatcher->resetCache();
    // Persist cached active trail information cache as if the request ended.
    if ($this->menuActiveTrail instanceof DestructableInterface) {
      $this->menuActiveTrail->destruct();
    }
    // Reset active trail cache.
    if ($this->menuActiveTrail instanceof CacheCollectorInterface) {
      $this->menuActiveTrail->reset();
    }
    // Allow module-specific code to prepare as well.
    $this->eventDispatcher->dispatch(TomeStaticEvents::REQUEST_PREPARE);
  }

}
