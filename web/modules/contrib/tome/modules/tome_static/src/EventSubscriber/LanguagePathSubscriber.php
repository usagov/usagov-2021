<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\tome_base\PathTrait;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds language negotiation URL prefixes to the list of paths to export.
 *
 * @internal
 */
class LanguagePathSubscriber implements EventSubscriberInterface {

  use PathTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the LanguagePathSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * Reacts to a collect paths event to add multilingual homepage paths.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function collectPaths(CollectPathsEvent $event) {
    $config = $this->configFactory->get('language.negotiation')->get('url');
    if (is_array($config) && isset($config['source'])) {
      if ($config['source'] === LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {
        foreach ($this->languageManager->getLanguages() as $language) {
          $langcode = $language->getId();
          if (!empty($config['prefixes'][$langcode])) {
            $prefix = $this->joinPaths('/', $config['prefixes'][$langcode]);
            $event->addPath($prefix, ['language_processed' => 'language_processed']);
            foreach ($event->getPaths(TRUE) as $path => $metadata) {
              if (!isset($metadata['language_processed']) && (!isset($metadata['language_prefix']) || $metadata['language_prefix'] === $langcode)) {
                if (!isset($metadata['original_path'])) {
                  $metadata['original_path'] = $path;
                }
                $metadata['language_processed'] = 'language_processed';
                if (isset($metadata['language_prefix'])) {
                  $event->replacePath($path, $this->joinPaths($prefix, $path), $metadata);
                }
                else {
                  $event->addPath($this->joinPaths($prefix, $path), $metadata);
                }
              }
            }
          }
        }
      }
      elseif ($config['source'] === LanguageNegotiationUrl::CONFIG_DOMAIN) {
        $paths = $event->getPaths(TRUE);
        foreach ($paths as $path => $metadata) {
          if (isset($metadata['language_processed']) && isset($metadata['langcode'])) {
            if ($metadata['langcode'] !== $this->languageManager->getCurrentLanguage()->getId()) {
              unset($paths[$path]);
            }
          }
        }
        $event->replacePaths($paths);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths', -1];
    return $events;
  }

}
