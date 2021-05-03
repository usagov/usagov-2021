<?php

namespace Drupal\Tests\webform\Functional\Exporter;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Tests for the webform exporter excluded.
 *
 * @group webform
 */
class WebformExporterExcludedTest extends WebformBrowserTestBase {

  /**
   * Test excluded exporters.
   */
  public function testExcludeExporters() {
    $this->drupalLogin($this->rootUser);

    // Check exporter options.
    $this->drupalGet('/admin/structure/webform/manage/contact/results/download');
    $this->assertRaw('<option value="delimited"');
    $this->assertRaw('<option value="table"');
    $this->assertRaw('<option value="json"');
    $this->assertRaw('<option value="yaml"');

    // Exclude the delimited exporter.
    \Drupal::configFactory()->getEditable('webform.settings')->set('export.excluded_exporters', ['delimited' => 'delimited'])->save();

    // Check delimited exporter excluded.
    $this->drupalGet('/admin/structure/webform/manage/contact/results/download');
    $this->assertNoRaw('<option value="delimited"');
    $this->assertRaw('<option value="table"');
    $this->assertRaw('<option value="json"');
    $this->assertRaw('<option value="yaml"');
  }

}
