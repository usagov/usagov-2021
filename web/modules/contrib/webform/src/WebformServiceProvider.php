<?php

namespace Drupal\webform;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\webform\Normalizer\WebformEntityReferenceItemNormalizer;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service Provider for Webform.
 */
class WebformServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    // Hal module is enabled, add our new normalizer for webform items.
    // Core 8.3 and above use hal module https://www.drupal.org/node/2830467.
    $manager = isset($modules['hal']) ? 'hal.link_manager' : 'rest.link_manager';
    if ($container->has($manager)) {
      $service_definition = new Definition(WebformEntityReferenceItemNormalizer::class, [
        new Reference($manager),
        new Reference('serializer.entity_resolver'),
      ]);
      // The priority must be higher than that of
      // serializer.normalizer.entity_reference_item.hal in
      // hal.services.yml.
      $service_definition->addTag('normalizer', ['priority' => 20]);
      $container->setDefinition('serializer.normalizer.webform_entity_reference_item', $service_definition);
    }

    // Overrides webform.exception_html_subscriber to support ExceptionEvent in
    // Drupal 9/Symfony 4.4.
    //
    // Issue #3113876: The "GetResponseForExceptionEvent::getException()"
    // method is deprecated since Symfony 4.4, use "getThrowable()" instead.
    // @see https://www.drupal.org/node/3113876
    if (floatval(\Drupal::VERSION) >= 9) {
      $definition = $container->getDefinition('webform.exception_html_subscriber');
      $definition->setClass('Drupal\webform\EventSubscriber\WebformDefaultExceptionHtmlSubscriber');
    }
  }

}
