<?php

declare(strict_types=1);

namespace Drupal\Tests\usa_workflow\Unit;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\usa_workflow\UsaWorkflowPolicy;

/**
 * Tests USA workflow policy decisions.
 *
 * @coversDefaultClass \Drupal\usa_workflow\UsaWorkflowPolicy
 * @group usa_workflow
 */
class UsaWorkflowPolicyTest extends UnitTestCase {

  /**
   * Tests separate exclusions for publish and delete.
   */
  public function testSeparateBundleExclusions(): void {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory->get('usa_workflow.settings')->willReturn($config->reveal());
    $config->get('enabled')->willReturn(TRUE);
    $config->get('restrict_self_publish')->willReturn(TRUE);
    $config->get('restrict_self_delete')->willReturn(TRUE);
    $config->get('excluded_publish_bundles')->willReturn(['node:basic_page']);
    $config->get('excluded_delete_bundles')->willReturn([]);

    $moderation_information = $this->prophesize(ModerationInformationInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $bundle_info = $this->prophesize(EntityTypeBundleInfoInterface::class);

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getEntityTypeId()->willReturn('node');
    $entity->bundle()->willReturn('basic_page');
    $moderation_information->isModeratedEntity($entity->reveal())->willReturn(TRUE);

    $policy = new UsaWorkflowPolicy(
      $config_factory->reveal(),
      $moderation_information->reveal(),
      $entity_type_manager->reveal(),
      $bundle_info->reveal(),
    );

    $this->assertFalse($policy->isEntityInScope($entity->reveal(), UsaWorkflowPolicy::OPERATION_PUBLISH));
    $this->assertTrue($policy->isEntityInScope($entity->reveal(), UsaWorkflowPolicy::OPERATION_DELETE));
  }

  /**
   * Tests new content is treated as owned by the current editor.
   */
  public function testNewEntitySelfPublishUsesCurrentUser(): void {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory->get('usa_workflow.settings')->willReturn($config->reveal());
    $config->get('enabled')->willReturn(TRUE);
    $config->get('restrict_self_publish')->willReturn(TRUE);
    $config->get('excluded_publish_bundles')->willReturn([]);

    $moderation_information = $this->prophesize(ModerationInformationInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $bundle_info = $this->prophesize(EntityTypeBundleInfoInterface::class);

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->isNew()->willReturn(TRUE);
    $entity->getEntityTypeId()->willReturn('node');
    $entity->bundle()->willReturn('basic_page');
    $moderation_information->isModeratedEntity($entity->reveal())->willReturn(TRUE);

    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(123);
    $account->hasPermission('usa approve own content')->willReturn(FALSE);

    $policy = new UsaWorkflowPolicy(
      $config_factory->reveal(),
      $moderation_information->reveal(),
      $entity_type_manager->reveal(),
      $bundle_info->reveal(),
    );

    $this->assertTrue($policy->isSelfPublishRestricted($entity->reveal(), $account->reveal()));
  }

}
