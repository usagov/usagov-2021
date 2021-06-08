<?php

namespace Drupal\Tests\dynamic_entity_reference\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Ensures that Dynamic Entity References field works correctly.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceTest extends WebDriverTestBase {

  /**
   * Escape key code.
   */
  const ESCAPE_KEY = 27;

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The another user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anotherUser;

  /**
   * Test entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $testEntity;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'dynamic_entity_reference',
    'entity_test',
    'node',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access administration pages',
    'view test entity',
    'administer entity_test fields',
    'administer entity_test content',
    'administer node fields',
    'administer node display',
    'access user profiles',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets the test up.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->anotherUser = $this->drupalCreateUser();
  }

  /**
   * Tests field settings of dynamic entity reference field.
   */
  public function testFieldSettings() {
    $assert_session = $this->assertSession();
    // Add EntityTestBundle for EntityTestWithBundle.
    EntityTestBundle::create([
      'id' => 'test',
      'label' => 'Test label',
      'description' => 'My test description',
    ])->save();
    // We will query on the first two characters of the second username.
    $autocomplete_query = mb_substr($this->anotherUser->label(), 0, 3);
    $this->testEntity = EntityTest::create([
      // Make this partially match the second user name.
      'name' => $autocomplete_query . $this->randomMachineName(5),
      'type' => 'entity_test',
    ]);
    $this->testEntity->save();

    $this->drupalLogin($this->adminUser);
    // Add a new dynamic entity reference field.
    $this->drupalGet('entity_test/structure/entity_test/fields/add-field');
    $select = $assert_session->selectExists('new_storage_type');
    $select->selectOption('dynamic_entity_reference');
    $label = $assert_session->fieldExists('label');
    $label->setValue('Foobar');
    // Wait for the machine name.
    $assert_session->waitForElementVisible('css', '[name="label"] + * .machine-name-value');
    $this->submitForm([], t('Save and continue'), 'field-ui-field-storage-add-form');
    $page = $this->getSession()->getPage();
    $entity_type_ids_select = $assert_session->selectExists('settings[entity_type_ids][]', $page);
    $entity_type_ids_select->selectOption('user');
    $entity_type_ids_select->selectOption('entity_test', TRUE);
    $assert_session->selectExists('cardinality', $page)
      ->selectOption(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $page->uncheckField('settings[exclude_entity_types]');
    $this->submitForm([], t('Save field settings'), 'field-storage-config-edit-form');
    $page = $this->getSession()->getPage();
    $autocomplete_field = $page->findField('default_value_input[field_foobar][0][target_id]');
    $autocomplete_field_1 = $page->findField('default_value_input[field_foobar][1][target_id]');
    $target_type_select = $assert_session->selectExists('default_value_input[field_foobar][0][target_type]');
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('entity_test'));
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('entity_test'));
    $target_type_select->selectOption('user');
    // Changing the selected value changes the autocomplete path for the
    // corresponding autocomplete field.
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('user'));
    // Changing the selected value of delta 0 doesn't change the autocomplete
    // path for delta 1 autocomplete field.
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('entity_test'));
    $target_type_select->selectOption('entity_test');
    // Changing the selected value changes the autocomplete path for the
    // corresponding autocomplete field.
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('entity_test'));
    // Changing the selected value of delta 0 doesn't change the autocomplete
    // path for delta 1 autocomplete field.
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $this->createAutoCompletePath('entity_test'));
    $page = $this->getSession()->getPage();
    $page->checkField('settings[entity_test][handler_settings][target_bundles][entity_test]');
    $assert_session->assertWaitOnAjaxRequest(20000);
    $page->checkField('settings[entity_test][handler_settings][auto_create]');
    $this->submitForm([], t('Save settings'), 'field-config-edit-form');
    $assert_session->pageTextContains('Saved Foobar configuration');
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_foobar');
    $this->assertEmpty($field_storage->getSetting('exclude_entity_types'));
    $this->assertEquals($field_storage->getSetting('entity_type_ids'), [
      'entity_test' => 'entity_test',
      'user' => 'user',
    ]);
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_foobar');
    $settings = $field_config->getSettings();
    $this->assertEquals($settings['entity_test']['handler'], 'default:entity_test');
    $this->assertNotEmpty($settings['entity_test']['handler_settings']);
    $this->assertEquals($settings['entity_test']['handler_settings']['target_bundles'], ['entity_test' => 'entity_test']);
    $this->assertTrue($settings['entity_test']['handler_settings']['auto_create']);
    $this->assertEmpty($settings['entity_test']['handler_settings']['auto_create_bundle']);
    $this->drupalGet('entity_test/add');
    $autocomplete_field = $page->findField('field_foobar[0][target_id]');
    $entity_type_field = $page->findField('field_foobar[0][target_type]');
    // Change to user.
    $entity_type_field->selectOption('user');
    $this->performAutocompleteQuery($autocomplete_query, $autocomplete_field);
    $this->selectAutocompleteOption();
    $assert_session->pageTextContains($this->anotherUser->label());
    // Change to entity_test, this should automatically clear the autocomplete
    // field.
    $entity_type_field->selectOption('entity_test');
    $this->assertEmpty($autocomplete_field->getValue());
    $this->performAutocompleteQuery($autocomplete_query, $autocomplete_field);
    $this->selectAutocompleteOption();
    $assert_session->pageTextContains($this->testEntity->label());
  }

  /**
   * Tests view modes in formatter of dynamic entity reference field.
   */
  public function testFieldFormatterViewModes() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'test_content']);
    $this->drupalGet('/admin/structure/types/manage/test_content/fields/add-field');
    $select = $assert_session->selectExists('new_storage_type');
    $select->selectOption('dynamic_entity_reference');
    $label = $assert_session->fieldExists('label');
    $label->setValue('Foobar');
    // Wait for the machine name.
    $assert_session->waitForElementVisible('css', '[name="label"] + * .machine-name-value');
    $this->submitForm([], t('Save and continue'), 'field-ui-field-storage-add-form');
    $page = $this->getSession()->getPage();
    $entity_type_ids_select = $assert_session->selectExists('settings[entity_type_ids][]', $page);
    $entity_type_ids_select->selectOption('user');
    $assert_session->selectExists('cardinality', $page)
      ->selectOption(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $page->uncheckField('settings[exclude_entity_types]');
    $this->submitForm([], t('Save field settings'), 'field-storage-config-edit-form');
    $this->drupalGet('admin/structure/types/manage/test_content/display');
    $page = $this->getSession()->getPage();
    $formats = $assert_session->selectExists('fields[field_foobar][type]', $page);
    $formats->selectOption('dynamic_entity_reference_entity_view');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $page = $this->getSession()->getPage();
    $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', 'compact', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', 'full', $page);
    // Edit field, turn on exclude entity types and check display again.
    $this->drupalGet('admin/structure/types/manage/test_content/fields/node.test_content.field_foobar/storage');
    $page->checkField('settings[exclude_entity_types]');
    $this->submitForm([], t('Save field settings'), 'field-storage-config-edit-form');
    $this->drupalGet('admin/structure/types/manage/test_content/display');
    $page = $this->getSession()->getPage();
    $formats = $assert_session->selectExists('fields[field_foobar][type]', $page);
    $formats->selectOption('dynamic_entity_reference_entity_view');
    $assert_session->assertWaitOnAjaxRequest();
    // Assert node view mode is set on default.
    $assert_session->responseContains("Content view mode: default");
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $page = $this->getSession()->getPage();
    // Assert we have multi select form items for view mode settings.
    $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', $page);
    $assert_session->responseContains("View mode for <em class=\"placeholder\">Test entity with bundle</em>");
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', 'default', $page);
    $assert_session->optionNotExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', 'rss', $page);
    $node_view_modes = $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', $page);
    $assert_session->responseContains("View mode for <em class=\"placeholder\">Content</em>");
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'default', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'full', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'rss', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'teaser', $page);
    // Select different select options and assert summary is changed properly.
    $node_view_modes->selectOption('teaser');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains("Content view mode: teaser");
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $node_view_modes->selectOption('rss');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains("Content view mode: rss");
  }

  /**
   * Creates auto complete path for the given target type.
   *
   * @param string $target_type
   *   The entity type id.
   *
   * @return string
   *   Auto complete paths for the target type.
   */
  protected function createAutoCompletePath($target_type) {
    $selection_settings = [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ];
    $data = serialize($selection_settings) . $target_type . "default:$target_type";
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    return Url::fromRoute('system.entity_autocomplete', [
      'target_type' => $target_type,
      'selection_handler' => "default:$target_type",
      'selection_settings_key' => $selection_settings_key,
    ])->toString();
  }

  /**
   * Peforms an autocomplete query on an element.
   *
   * @param string $autocomplete_query
   *   String to search for.
   * @param \Behat\Mink\Element\NodeElement $autocomplete_field
   *   Field to search in.
   */
  protected function performAutocompleteQuery($autocomplete_query, NodeElement $autocomplete_field) {
    $autocomplete_field->setValue($autocomplete_query);
    $autocomplete_field->keyDown(' ');
    $this->assertSession()->waitOnAutocomplete();
  }

  /**
   * Selects the autocomplete result with the given delta.
   *
   * @param int $delta
   *   Delta of item to select. Starts from 0.
   */
  protected function selectAutocompleteOption($delta = 0) {
    // Press the down arrow to select the nth option.
    /** @var \Behat\Mink\Element\NodeElement $element */
    $element = $this->getSession()->getPage()->findAll('css', '.ui-autocomplete.ui-menu li.ui-menu-item')[$delta];
    $element->click();
  }

}
