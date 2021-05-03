<?php

namespace Drupal\Tests\paragraphs\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests Paragraphs update path from 8.x-1.1.
 *
 * @group paragraphs
 * @group legacy
 */
class ParagraphsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../fixtures/update/drupal-8.8.standard.paragraphs_demo.php.gz',
    ];
  }

  /**
   * Tests the update path.
   */
  public function testUpdatePath() {
    // Run updates.
    $this->runUpdates();
  }

}
