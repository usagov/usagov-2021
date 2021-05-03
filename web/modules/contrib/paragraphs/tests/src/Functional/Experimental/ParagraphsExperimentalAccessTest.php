<?php

namespace Drupal\Tests\paragraphs\Functional\Experimental;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the access check of paragraphs.
 *
 * @group paragraphs
 */
class ParagraphsExperimentalAccessTest extends ParagraphsExperimentalTestBase {

  use FieldUiTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'content_translation',
    'image',
    'field',
    'field_ui',
    'block',
    'language',
    'node'
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    ConfigurableLanguage::create(['id' => 'de', 'label' => '1German'])->save();
    ConfigurableLanguage::create(['id' => 'fr', 'label' => '2French'])->save();
    $this->addParagraphedContentType('paragraphed_content_demo', 'field_paragraphs_demo');
    $this->loginAsAdmin([
      'administer site configuration',
      'administer content translation',
      'administer languages',
    ]);
    $this->addParagraphsType('nested_paragraph');
    $this->addParagraphsField('nested_paragraph', 'field_paragraphs_demo', 'paragraph');
    $this->addParagraphsType('images');
    static::fieldUIAddNewField('admin/structure/paragraphs_type/images', 'images_demo', 'Images', 'image', ['cardinality' => -1], ['settings[alt_field]' => FALSE]);
    $this->addParagraphsType('text_image');
    static::fieldUIAddNewField('admin/structure/paragraphs_type/text_image', 'image_demo', 'Images', 'image', ['cardinality' => -1], ['settings[alt_field]' => FALSE]);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/text_image', 'text_demo', 'Text', 'text_long', [], []);
    $this->addParagraphsType('text');
    static::fieldUIAddExistingField('admin/structure/paragraphs_type/text', 'field_text_demo', 'Text', []);
    $edit = [
      'entity_types[node]' => TRUE,
      'entity_types[paragraph]' => TRUE,
      'settings[node][paragraphed_content_demo][translatable]' => TRUE,
      'settings[node][paragraphed_content_demo][fields][field_paragraphs_demo]' => FALSE,
      'settings[paragraph][images][translatable]' => TRUE,
      'settings[paragraph][text_image][translatable]' => TRUE,
      'settings[paragraph][text][translatable]' => TRUE,
      'settings[paragraph][nested_paragraph][translatable]' => TRUE,
      'settings[paragraph][nested_paragraph][fields][field_paragraphs_demo]' => FALSE,
      'settings[paragraph][nested_paragraph][settings][language][language_alterable]' => TRUE,
      'settings[paragraph][images][fields][field_images_demo]' => TRUE,
      'settings[paragraph][text_image][fields][field_image_demo]' => TRUE,
      'settings[paragraph][text_image][fields][field_text_demo]' => TRUE,
      'settings[node][paragraphed_content_demo][settings][language][language_alterable]' => TRUE
    ];
    $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));

    $view_display = \Drupal::service('entity_display.repository')->getViewDisplay('paragraph', 'images');
    $view_display->setComponent('field_images_demo', ['settings' => ['image_style' => 'medium']]);
    $view_display->save();
  }

  /**
   * Tests the Paragraph access and permissions.
   */
  public function testParagraphAccessCheck() {
    $permissions = [
      'administer site configuration',
      'administer node display',
      'administer paragraph display',
      'create paragraphed_content_demo content',
      'edit any paragraphed_content_demo content',
    ];
    $this->loginAsAdmin($permissions);

    // Remove the "access content" for anonymous users. That results in
    // anonymous users not being able to "view" the host entity.
    /* @var Role $role */
    $role = \Drupal::entityTypeManager()
      ->getStorage('user_role')
      ->load(RoleInterface::ANONYMOUS_ID);
    $role->revokePermission('access content');
    $role->save();

    // Set field_images from demo to private file storage.
    $edit = array(
      'settings[uri_scheme]' => 'private',
    );
    $this->drupalPostForm('admin/structure/paragraphs_type/images/fields/paragraph.images.field_images_demo/storage', $edit, t('Save field settings'));

    // Use the experimental widget.
    $form_display = EntityFormDisplay::load('node.paragraphed_content_demo.default')
      ->setComponent('field_paragraphs_demo', ['type' => 'paragraphs']);
    $form_display->save();
    // Create a new demo node.
    $this->drupalGet('node/add/paragraphed_content_demo');

    // Add a new Paragraphs images item.
    $this->drupalPostForm(NULL, NULL, t('Add images'));

    $images = $this->getTestFiles('image');

    // Create a file, upload it.
    $file_system = \Drupal::service('file_system');
    $file_system->copy($images[0]->uri, 'temporary://privateImage.jpg');
    $file_path = $this->container->get('file_system')
      ->realpath('temporary://privateImage.jpg');

    // Create a file, upload it.
    $file_system->copy($images[1]->uri, 'temporary://privateImage2.jpg');
    $file_path_2 = $this->container->get('file_system')
      ->realpath('temporary://privateImage2.jpg');

    $edit = array(
      'title[0][value]' => 'Security test node',
      'files[field_paragraphs_demo_0_subform_field_images_demo_0][]' => $file_path,
    );

    $this->drupalPostForm(NULL, $edit, t('Upload'));

    $edit = array(
      'files[field_paragraphs_demo_0_subform_field_images_demo_1][]' => $file_path_2,
    );

    $this->drupalPostForm(NULL, $edit, t('Upload'));
    $this->drupalPostForm(NULL,  [], t('Preview'));
    $image_style = ImageStyle::load('medium');
    $img1_url = $image_style->buildUrl('private://' . date('Y-m') . '/privateImage.jpg');
    $image_url = file_url_transform_relative($img1_url);
    $this->assertSession()->responseContains($image_url, 'Image was found in preview');
    $this->clickLink(t('Back to content editing'));
    $this->drupalPostForm(NULL, [], t('Save'));

    $node = $this->drupalGetNodeByTitle('Security test node');

    $this->drupalGet('node/' . $node->id());

    // Check the text and image after publish.
    $this->assertSession()->responseContains($image_url, 'Image was found in content');

    $this->drupalGet($img1_url);
    $this->assertSession()->statusCodeEquals(200);

    // Logout to become anonymous.
    $this->drupalLogout();

    // @todo Requesting the same $img_url again triggers a caching problem on
    // drupal.org test bot, thus we request a different file here.
    $img_url = $image_style->buildUrl('private://' . date('Y-m') . '/privateImage2.jpg');
    $image_url = file_url_transform_relative($img_url);
    // Check the text and image after publish. Anonymous should not see content.
    $this->assertSession()->responseNotContains($image_url, 'Image was not found in content');

    $this->drupalGet($img_url);
    $this->assertSession()->statusCodeEquals(403);

    // Login as admin with no delete permissions.
    $this->loginAsAdmin($permissions);
    // Create a new demo node.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->drupalPostForm(NULL, NULL, t('Add text'));
    $this->assertSession()->pageTextContains('Text');
    $edit = [
      'title[0][value]' => 'delete_permissions',
      'field_paragraphs_demo[0][subform][field_text_demo][0][value]' => 'Test',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Edit the node.
    $this->clickLink(t('Edit'));
    // Check the remove button is present.
    $this->assertNotNull($this->xpath('//*[@name="field_paragraphs_demo_0_remove"]'));
    // Delete the Paragraph and save.
    $this->drupalPostForm(NULL, [], 'field_paragraphs_demo_0_remove');
    $this->drupalPostForm(NULL, [], t('Save'));
    $node = $this->getNodeByTitle('delete_permissions');
    $this->assertSession()->addressEquals('node/' . $node->id());

    // Create an unpublished Paragraph and assert if it is displayed for the
    // user.
    $permissions = [
      'create paragraphed_content_demo content',
      'edit any paragraphed_content_demo content',
      'view unpublished paragraphs',
      'administer paragraph form display',
    ];
    $this->loginAsAdmin($permissions);
    $edit = [
      'fields[status][region]' => 'content',
      'fields[status][type]' => 'boolean_checkbox'
    ];
    $this->drupalPostForm('admin/structure/paragraphs_type/text/form-display', $edit, 'Save');
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->drupalPostForm(NULL, NULL, t('Add text'));
    $this->assertSession()->pageTextContains('Text');
    $edit = [
      'title[0][value]' => 'unpublished_permissions',
      'field_paragraphs_demo[0][subform][field_text_demo][0][value]' => 'recognizable_test',
      'field_paragraphs_demo[0][subform][status][value]' => FALSE
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertSession()->pageTextContains('recognizable_test');
    $this->assertSession()->responseContains('paragraph--unpublished');
    $this->drupalLogout();
    $node = $this->drupalGetNodeByTitle('unpublished_permissions');

    // Login as an user without the view unpublished Paragraph permission.
    $user = $this->drupalCreateUser([
      'administer nodes',
      'edit any paragraphed_content_demo content',
    ]);
    $this->drupalLogin($user);
    // Assert that the Paragraph is not displayed.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextNotContains('recognizable_test');
    $this->assertSession()->responseNotContains('paragraph--unpublished');
    // Grant to the user the view unpublished Paragraph permission.
    $this->grantPermissions(Role::load(Role::AUTHENTICATED_ID), ['view unpublished paragraphs']);
    // Assert that the Paragraph is displayed.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('recognizable_test');
    $this->assertSession()->responseContains('paragraph--unpublished');

    // Grant to the user the administer Paragraphs settings permission.
    $this->grantPermissions(Role::load(Role::AUTHENTICATED_ID), ['administer paragraphs settings']);
    // Disable the show unpublished Paragraphs setting.
    $this->drupalPostForm('admin/config/content/paragraphs', ['show_unpublished' => FALSE], 'Save configuration');
    // Assert that the Paragraph is not displayed even if the user has the
    // permission to do so.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextNotContains('recognizable_test');
    $this->assertSession()->responseNotContains('paragraph--unpublished');
    // Enable the show unpublished Paragraphs setting.
    $this->drupalPostForm('admin/config/content/paragraphs', ['show_unpublished' => TRUE], 'Save configuration');
    // Assert that the Paragraph is displayed when the user has the permission
    // to do so.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('recognizable_test');
    $this->assertSession()->responseContains('paragraph--unpublished');
  }

  /**
   * Tests the Paragraph validation with filter access.
   */
  public function testParagraphsTextFormatValidation() {
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ]);
    $filtered_html_format->save();
    $permissions = [
      'create paragraphed_content_demo content',
      'edit any paragraphed_content_demo content',
      $filtered_html_format->getPermissionName()
    ];
    $this->loginAsAdmin($permissions);
    // Create a node with a Text Paragraph using the filtered html format.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->drupalPostForm(NULL, NULL, t('Add text'));
    $this->assertSession()->pageTextContains('Text');
    $edit = [
      'title[0][value]' => 'access_validation_test',
      'field_paragraphs_demo[0][subform][field_text_demo][0][value]' => 'Test',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertSession()->pageTextContains('paragraphed_content_demo access_validation_test has been created.');
    $this->drupalLogout();
    // Login as an user without the Text Format permission.
    $user = $this->drupalCreateUser([
      'administer nodes',
      'edit any paragraphed_content_demo content',
    ]);
    $this->drupalLogin($user);
    $node = $this->getNodeByTitle('access_validation_test');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertSession()->pageTextContains('paragraphed_content_demo access_validation_test has been updated.');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_demo_0_collapse');
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertSession()->pageTextContains('paragraphed_content_demo access_validation_test has been updated.');
    $this->assertSession()->pageTextNotContains('The value you selected is not a valid choice.');
  }

}
