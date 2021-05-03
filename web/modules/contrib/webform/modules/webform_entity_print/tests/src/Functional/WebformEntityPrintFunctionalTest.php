<?php

namespace Drupal\Tests\webform_entity_print\Functional;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Webform entity print test.
 *
 * @group webform_browser
 */
class WebformEntityPrintFunctionalTest extends WebformEntityPrintFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['image', 'webform_entity_print_test'];

  /**
   * Test entity print.
   */
  public function testEntityPrint() {
    global $base_path, $base_url;

    $this->drupalLogin($this->rootUser);

    /**************************************************************************/
    // PDF link default.
    /**************************************************************************/

    $webform = Webform::load('test_entity_print');
    $sid = $this->postSubmissionTest($webform);
    $submission = WebformSubmission::load($sid);

    // Check PDF link to html mode enabled.
    $this->drupalGet("/admin/structure/webform/manage/test_entity_print/submission/$sid");
    $this->assertRaw('<div class="webform-entity-print-links"><a href="' . $base_path . 'print/pdf/webform_submission/' . $sid . '?view_mode=html" class="button webform-entity-print-link webform-entity-print-link-pdf">Download PDF</a></div>');

    // Check PDF link to table mode enabled.
    $this->drupalGet("/admin/structure/webform/manage/test_entity_print/submission/$sid/table");
    $this->assertRaw('<div class="webform-entity-print-links"><a href="' . $base_path . 'print/pdf/webform_submission/' . $sid . '?view_mode=table" class="button webform-entity-print-link webform-entity-print-link-pdf">Download PDF</a></div>');

    // Check PDF document HTML view mode.
    $this->drupalGet("/print/pdf/webform_submission/$sid/debug", ['query' => ['view_mode' => 'html']]);
    $this->assertRaw('<div class="webform-entity-print-header"><h1>' . Html::escape($submission->label()) . '</h1></div>');

    // Check text field.
    $this->assertRaw('<label>textfield</label>');

    // Check PDF document includes custom style tag with webform and
    // webform entity print global css.
    $this->assertRaw('<style type="text/css" media="all">
/** custom webform css **/
/* Remove page margins and padding and rely on the PDF generator\'s default margins. */
body {
  margin: 0;
}

.page {
  padding: 0;
}
</style>');

    // Check image.
    $image_uri = $base_url . '/system/files/webform/test_entity_print/1/image_file.gif';
    $image_token_query = [WEBFORM_ENTITY_PRINT_IMAGE_TOKEN => _webform_entity_print_token_generate($image_uri)];
    $this->assertRaw('?' . UrlHelper::buildQuery($image_token_query));

    // Check image style.
    $image_style_uri = $base_url . '/system/files/styles/thumbnail/private/webform/test_entity_print/1/image_file_style.gif';
    $image_style_token_query = [WEBFORM_ENTITY_PRINT_IMAGE_TOKEN => _webform_entity_print_token_generate($image_style_uri)];
    $this->assertRaw('&' . UrlHelper::buildQuery($image_style_token_query));

    // Check image access.
    $this->drupalLogout();
    $this->drupalGet($image_uri);
    $this->assertRaw('Please login to access the uploaded file.');
    $this->drupalGet($image_uri, ['query' => $image_token_query]);
    $this->assertNoRaw('Please login to access the uploaded file.');
    $this->assertResponse(200);
    $this->drupalLogin($this->rootUser);

    // Check PDF document Table view mode.
    $this->drupalGet("/print/pdf/webform_submission/$sid/debug", ['query' => ['view_mode' => 'table']]);
    $this->assertRaw('<div class="webform-entity-print-header"><h1>' . Html::escape($submission->label()) . '</h1></div>');
    $this->assertNoRaw('<label>textfield</label>');
    $this->assertRaw('<th>textfield</th>');
    $this->assertRaw('<table class="webform-submission-table" data-striping="1">');

    // Check PDF link customizable.
    $edit = [
      'third_party_settings[webform_entity_print][export_types][pdf][link_text]' => 'Generate PDF',
    ];
    $this->drupalPostForm('/admin/structure/webform/config', $edit, 'Save configuration');
    $this->drupalGet("/admin/structure/webform/manage/test_entity_print/submission/$sid");
    $this->assertNoLink('Download PDF');
    $this->assertLink('Generate PDF');

    // Check PDF link disabled.
    $edit = [
      'third_party_settings[webform_entity_print][export_types][pdf][enabled]' => FALSE,
    ];
    $this->drupalPostForm('/admin/structure/webform/config', $edit, 'Save configuration');
    $this->drupalGet("/admin/structure/webform/manage/test_entity_print/submission/$sid");
    $this->assertNoLink('Download PDF');

    /**************************************************************************/
    // Exporter.
    /**************************************************************************/

    /** @var \Drupal\webform\WebformSubmissionExporterInterface $submission_exporter */
    $submission_exporter = \Drupal::service('webform_submission.exporter');
    $submission_exporter->setWebform($webform);
    $submission_exporter->setExporter();

    // Download tar ball archive with PDF documents.
    $edit = [
      'exporter' => 'webform_entity_print:pdf',
    ];
    $this->drupalPostForm('/admin/structure/webform/manage/test_entity_print/results/download', $edit, 'Download');

    // Load the tar and get a list of files.
    $files = $this->getArchiveContents($submission_exporter->getArchiveFilePath());
    $this->assertEquals(["submission-$sid.pdf" => "submission-$sid.pdf"], $files);

    /**************************************************************************/
    // PDF link custom.
    /**************************************************************************/

    $webform = Webform::load('test_entity_print_custom');
    $sid = $this->postSubmissionTest($webform);

    // Check custom PDF link to html mode enabled.
    $this->drupalGet("/admin/structure/webform/manage/test_entity_print_custom/submission/$sid");
    $this->assertRaw('<div class="webform-entity-print-links"><a href="' . $base_path . 'print/pdf/webform_submission/' . $sid . '?view_mode=html" style="color: red" class="custom-class webform-entity-print-link webform-entity-print-link-pdf">{custom link text}</a></div>');

    // Check custom PDF document HTML view mode.
    $this->drupalGet("/print/pdf/webform_submission/$sid/debug", ['query' => ['view_mode' => 'html']]);
    $this->assertRaw('<div class="webform-entity-print-header"><div>{custom header}</div></div>');
    $this->assertRaw('<div class="webform-entity-print-footer"><div>{custom footer}</div></div>');

    // Check PDF document includes custom style tag with webform and
    // webform entity print global css.
    $this->assertRaw('<style type="text/css" media="all">
/** custom webform css **/
/* Remove page margins and padding and rely on the PDF generator\'s default margins. */
body {
  margin: 0;
}

.page {
  padding: 0;
}
/** custom webform print css **/
</style>');
  }

  /**
   * Get archive contents.
   *
   * @param string $filepath
   *   Archive file path.
   *
   * @return array
   *   Array of archive contents.
   */
  protected function getArchiveContents($filepath) {
    if (strpos($filepath, '.zip') !== FALSE) {
      $archive = new \ZipArchive();
      $archive->open($filepath);
      $files = [];
      for ($i = 0; $i < $archive->numFiles; $i++) {
        $files[] = $archive->getNameIndex($i);
      }
    }
    else {
      $archive = new \Archive_Tar($filepath, 'gz');
      $files = [];
      foreach ($archive->listContent() as $file_data) {
        $files[] = $file_data['filename'];
      }
    }
    return array_combine($files, $files);
  }

}
