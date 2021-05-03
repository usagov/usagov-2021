<?php

namespace Drupal\Tests\workbench_email\Functional;

use Composer\Semver\Semver;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Defines a class for testing upgrade path.
 *
 * @group workbench_email
 */
class RecipientPluginUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../fixtures/update/recipient-plugins.php.gz',
    ];
  }

  /**
   * Tests workbench_email_update_8001().
   */
  public function testUpdatePath() {
    if (Semver::satisfies(\Drupal::VERSION, '~9')) {
      $this->markTestSkipped('This test is only for Drupal 8');
    }
    $this->container->get('module_installer')->install(['block']);
    $this->runUpdates();
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\workbench_email\TemplateInterface $template */
    $template = $entity_type_manager->getStorage('workbench_email_template')->load('send');
    $nodes = $entity_type_manager->getStorage('node')->loadByProperties([
      'title' => 'test node',
    ]);
    $recipients = $template->getRecipients(reset($nodes));
    $expected = [
      // User with approver role.
      'bob@example.com',
      // Author.
      'admin@example.com',
      // Email field on node.
      'terri@example.com',
    ];
    $this->assertEquals(sort($expected), sort($recipients));
    $this->assertNull($template->get('author'));
    $this->assertNull($template->get('fields'));
    $this->assertNull($template->get('roles'));
  }

}
