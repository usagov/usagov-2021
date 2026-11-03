<?php

namespace Drupal\usagov_menus;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\usagov_menus\Menu\UsagovMenuActiveTrail;
use Drupal\usagov_menus\Menu\UsagovMenuLinkManager;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps in menu services that prefer the original node_menus link.
 *
 * Replaces the former correctActiveTrail core patch. Only the classes change;
 * core's constructor arguments are kept, so this works on Drupal 10 and 11.
 */
class UsagovMenusServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('plugin.manager.menu.link')) {
      $container->getDefinition('plugin.manager.menu.link')
        ->setClass(UsagovMenuLinkManager::class)
        ->addMethodCall('setDatabase', [new Reference('database')]);
    }
    if ($container->hasDefinition('menu.active_trail')) {
      $container->getDefinition('menu.active_trail')
        ->setClass(UsagovMenuActiveTrail::class)
        ->addMethodCall('setUsagovPathMatcher', [new Reference('path.matcher')]);
    }
  }

}
