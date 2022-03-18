<?php

namespace Drupal\usa_workflow;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider for the usa_workflow module.
 *
 * This is used to alter the content moderation services to add our custom
 * permissions. This can't be done via a normal service declaration as
 * decorating optional services is not supported.
 */
class UsaWorkflowServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['content_moderation'])) {

      // Decorate the state transition validation service.
      $state_transition_definition = new Definition(OwnerStateTransitionValidation::class, [
        new Reference('content_moderation.moderation_information'),
      ]);
      $state_transition_definition->setPublic(TRUE);
      $state_transition_definition->setDecoratedService('content_moderation.state_transition_validation');
      $container->setDefinition('usa_workflow.state_transition_validation', $state_transition_definition);
    }
  }

}
