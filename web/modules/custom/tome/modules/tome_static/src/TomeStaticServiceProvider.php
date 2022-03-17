<?php

namespace Drupal\tome_static;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\tome_static\EventSubscriber\LanguagePathSubscriber;
use Drupal\tome_static\EventSubscriber\PageCacheRequestPrepareSubscriber;
use Drupal\tome_static\EventSubscriber\RedirectPathSubscriber;
use Drupal\tome_static\PageCache\RequestPolicy\DynamicRequestPolicy;
use Drupal\tome_static\StackMiddleware\ResettablePageCache;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services in the container.
 *
 * @internal
 */
class TomeStaticServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['language'])) {
      $container->register('tome_static.language_path_subscriber', LanguagePathSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('config.factory'))
        ->addArgument(new Reference('language_manager'));
    }
    if (isset($modules['redirect'])) {
      $container->register('tome_static.redirect_path_subscriber', RedirectPathSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('language_manager'));
    }
    if (isset($modules['dynamic_page_cache'])) {
      $container->register('tome_static.dynamic_page_cache_request_policy', DynamicRequestPolicy::class)
        ->setDecoratedService('dynamic_page_cache_request_policy');
    }
    if (isset($modules['page_cache'])) {
      $container->setDefinition('tome_static.http_middleware.page_cache', new ChildDefinition('http_middleware.page_cache'))
        ->setClass(ResettablePageCache::class)
        ->setDecoratedService('http_middleware.page_cache');
      $container->register('tome_static.page_cache_request_prepare_subscriber', PageCacheRequestPrepareSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('http_middleware.page_cache'));
    }
  }

}
