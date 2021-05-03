<?php

namespace Drupal\Tests\webform\Functional\Composite;

use Drupal\file\Entity\File;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Tests\webform\Functional\Element\WebformElementManagedFileTestBase;

/**
 * Tests for custom composite element.
 *
 * @group webform
 */
class WebformCompositeCustomFileTest extends WebformElementManagedFileTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_composite_custom_file'];

  /**
   * Test custom composite element.
   */
  public function testCustom() {
    $webform = Webform::load('test_composite_custom_file');

    $first_file = $this->files[0];

    /**************************************************************************/

    // Upload file while adding a new row to the composite element.
    $edit = [
      'webform_custom_composite_file[items][0][_item_][textfield]' => '{textfield}',
      'files[webform_custom_composite_file_items_0__item__managed_file]' => \Drupal::service('file_system')->realpath($first_file->uri),
    ];
    $this->drupalPostForm('webform/test_composite_custom_file', $edit, 'webform_custom_composite_file_table_add');

    // Check that file was uploaded.
    $this->assertRaw(basename($first_file->uri));

    // Add another empty row and check that file is still uploaded and attached.
    $this->drupalPostForm(NULL, [], 'webform_custom_composite_file_table_add');
    $this->assertRaw(basename($first_file->uri));

    // Submit the file and the text field.
    $this->drupalPostForm(NULL, [], 'Submit');
    $sid = $this->getLastSubmissionId($webform);

    $webform_submission = WebformSubmission::load($sid);

    $fid = $this->getLastFileId();
    /** @var \Drupal\file\FileInterface $file */
    $file = File::load($fid);

    // Check the composite file is attached to the email.
    $this->assertRaw('<label>Attachments</label>');
    $this->assertRaw('<strong><a href="' . file_create_url($file->getFileUri()) . '">' . $file->getFileName() . '</a></strong> (text/plain) - 1 KB');

    // Check file permanent.
    $this->assertTrue($file->isPermanent());

    // Check file upload.
    $element_data = $webform_submission->getElementData('webform_custom_composite_file');
    $this->assertEqual($element_data[0]['managed_file'], $fid, 'Test file was upload to the current submission');

    // Check test file file usage.
    $this->assertIdentical(['webform' => ['webform_submission' => [$sid => '1']]], $this->fileUsage->listUsage($file), 'The file has 1 usage.');

    // Check test file uploaded file path.
    $this->assertEqual($file->getFileUri(), 'private://webform/test_composite_custom_file/' . $sid . '/' . $first_file->filename);

    // Check that test file exists.
    $this->assertFileExists($file->getFileUri());

    // Login as root user.
    $this->drupalLogin($this->rootUser);

    // Check that the file exists on the submission edit form.
    $this->drupalGet("/admin/structure/webform/manage/test_composite_custom_file/submission/$sid/edit");
    $this->assertLink($file->getFileName());

    // Check that test file still exists as more items are be added to the
    // composite element.
    $this->drupalPostForm(NULL, [], 'webform_custom_composite_file_table_add');
    $this->assertLink($file->getFileName());
  }

}
