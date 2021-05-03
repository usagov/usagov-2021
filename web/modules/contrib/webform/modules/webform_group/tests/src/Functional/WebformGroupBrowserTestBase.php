<?php

namespace Drupal\Tests\webform_group\Functional;

use Drupal\group\Entity\GroupRole;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;
use Drupal\Tests\webform\Traits\WebformAssertLegacyTrait;
use Drupal\Tests\webform_node\Traits\WebformNodeBrowserTestTrait;

/**
 * Base class for webform group tests.
 */
abstract class WebformGroupBrowserTestBase extends GroupBrowserTestBase {

  use WebformAssertLegacyTrait;
  use WebformBrowserTestTrait;
  use WebformNodeBrowserTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['webform_group', 'webform_group_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Allow all roles to view webform nodes.
    /** @var \Drupal\group\Entity\GroupRoleInterface[] $group_roles */
    $group_roles = GroupRole::loadMultiple();
    foreach ($group_roles as $group_role) {
      $group_role->grantPermission('view group_node:webform entity');
      $group_role->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->purgeSubmissions();
    parent::tearDown();
  }

}
