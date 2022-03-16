<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds route paths to the list of paths to export.
 *
 * @internal
 */
class RoutePathSubscriber implements EventSubscriberInterface {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs the RoutePathSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(RouteProviderInterface $route_provider, LanguageManagerInterface $language_manager) {
    $this->routeProvider = $route_provider;
    $this->languageManager = $language_manager;
  }

  /**
   * Reacts to a collect paths event.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function collectPaths(CollectPathsEvent $event) {
    $language_none = $this->languageManager
      ->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      try {
        $url = Url::fromRoute($route_name, [], [
          'language' => $language_none,
        ]);
        $path = $url->toString();
        if ($path && $url->access()) {
          $event->addPath(parse_url($path, PHP_URL_PATH));
        }
      }
      catch (\Exception $e) {
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths'];
    return $events;
  }

}
