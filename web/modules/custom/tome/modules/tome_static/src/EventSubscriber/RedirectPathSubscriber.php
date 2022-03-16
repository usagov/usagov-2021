<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\tome_base\PathTrait;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds redirect placeholder paths to the list of paths to export.
 *
 * @internal
 */
class RedirectPathSubscriber implements EventSubscriberInterface {

  use PathTrait;

  /**
   * The path placeholder prefix.
   *
   * @var string
   */
  const PLACEHOLDER_PREFIX = '_redirect';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs the RedirectPathSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Reacts to a collect paths event.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function collectPaths(CollectPathsEvent $event) {
    $langcodes = array_keys($this->languageManager->getLanguages());
    $storage = $this->entityTypeManager->getStorage('redirect');
    $langcode_key = $storage->getEntityType()->getKey('langcode');
    $langcodes[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    foreach ($langcodes as $langcode) {
      foreach ($storage->getQuery()->condition($langcode_key, $langcode)->execute() as $entity_id) {
        $event->addPath(implode(':', [
          static::PLACEHOLDER_PREFIX,
          $entity_id,
        ]), [
          'language_prefix' => $langcode,
        ]);
      }
    }
  }

  /**
   * Reacts to a path placeholder event.
   *
   * @param \Drupal\tome_static\Event\PathPlaceholderEvent $event
   *   The path placeholder event.
   */
  public function replacePathPlaceholder(PathPlaceholderEvent $event) {
    $path = $event->getPath();
    $pattern = '/' . static::PLACEHOLDER_PREFIX . ':[^\/:]+/';
    if (preg_match($pattern, $path, $matches)) {
      list(, $entity_id) = explode(':', $matches[0]);
      $entity = $this->entityTypeManager->getStorage('redirect')->load($entity_id);
      if (!$entity || !($entity instanceof Redirect)) {
        $event->setInvalid();
        return;
      }
      if (!empty($entity->redirect_source->path) && !UrlHelper::isExternal($entity->redirect_source->path)) {
        $event->setPath(preg_replace($pattern, $entity->redirect_source->path, $path));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths'];
    $events[TomeStaticEvents::PATH_PLACEHOLDER][] = ['replacePathPlaceholder'];
    return $events;
  }

}
