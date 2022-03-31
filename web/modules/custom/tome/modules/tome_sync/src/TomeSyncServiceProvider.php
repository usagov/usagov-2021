<?php

namespace Drupal\tome_sync;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\tome_sync\EventSubscriber\BookEventSubscriber;
use Drupal\tome_sync\EventSubscriber\LanguageConfigEventSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services in the container.
 *
 * @internal
 */
class TomeSyncServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['language'])) {
      $container->register('tome_sync.language_config_event_subscriber', LanguageConfigEventSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('config.storage.sync'));
    }
    if (isset($modules['book'])) {
      $container->register('tome_sync.book_event_subscriber', BookEventSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('database'))
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('file_system'));
    }
  }

}
