<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds entity placeholder paths to the list of paths to export.
 *
 * @internal
 */
class EntityPathSubscriber implements EventSubscriberInterface {

  /**
   * The path placeholder prefix.
   *
   * @var string
   */
  const PLACEHOLDER_PREFIX = '_entity';

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
   * Constructs the EntityPathSubscriber object.
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
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (is_a($entity_type->getClass(), '\Drupal\Core\Entity\ContentEntityInterface', TRUE) && $entity_type->hasLinkTemplate('canonical')) {
        if ($entity_type->hasLinkTemplate('edit-form') && $entity_type->getLinkTemplate('edit-form') === $entity_type->getLinkTemplate('canonical')) {
          continue;
        }
        $storage = $this->entityTypeManager->getStorage($entity_type->id());
        if ($entity_type->isTranslatable() && $langcode_key = $entity_type->getKey('langcode')) {
          foreach ($langcodes as $langcode) {
            foreach ($storage->getQuery()->condition($langcode_key, $langcode)->execute() as $entity_id) {
              $event->addPath(implode(':', [
                static::PLACEHOLDER_PREFIX,
                $entity_type->id(),
                $langcode,
                $entity_id,
              ]), [
                'language_processed' => 'language_processed',
                'langcode' => $langcode,
              ]);
            }
          }
        }
        else {
          foreach ($storage->getQuery()->execute() as $entity_id) {
            $event->addPath(implode(':', [
              static::PLACEHOLDER_PREFIX,
              $entity_type->id(),
              $default_langcode,
              $entity_id,
            ]));
          }
        }
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
    if (strpos($path, static::PLACEHOLDER_PREFIX . ':') === 0) {
      $event->stopPropagation();
      list(, $entity_type_id, $langcode, $entity_id) = explode(':', $path);
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
      if (!$entity || (!$entity instanceof ContentEntityInterface) || !$entity->hasTranslation($langcode)) {
        $event->setInvalid();
        return;
      }
      $entity = $entity->getTranslation($langcode);
      $url = $entity->toUrl('canonical');
      if (!$entity->access('view') || ($entity->isDefaultTranslation() && !$url->access())) {
        $event->setInvalid();
        return;
      }
      $event->setPath(parse_url($url->toString(), PHP_URL_PATH));
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
