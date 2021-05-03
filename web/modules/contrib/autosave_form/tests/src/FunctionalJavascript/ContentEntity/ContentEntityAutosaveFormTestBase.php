<?php

namespace Drupal\Tests\autosave_form\FunctionalJavascript\ContentEntity;

use Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\autosave_form\FunctionalJavascript\AutosaveFormTestBase;
use Drupal\user\EntityOwnerInterface;

/**
 * Base test class for testing autosave support for entity forms.
 */
abstract class ContentEntityAutosaveFormTestBase extends AutosaveFormTestBase {

  /**
   * The entity type to be tested.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle of the entity type to be tested.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The original entity title.
   *
   * @var string
   */
  protected $originalEntityTitle;

  /**
   * The changed entity title.
   *
   * @var string
   */
  protected $changedEntityTitle;

  /**
   * The name of the field with unlimited cardinality to test ajax requests.
   *
   * @var string
   */
  protected $unlimitedCardinalityField = 'autosave_unlimited_field';

  /**
   * The name of the required test field.
   *
   * @var string
   */
  protected $requiredField = 'autosave_required_field';

  /**
   * Count of the changes to make.
   *
   * @var int
   */
  protected $testAutosaveFormExistingEntityChangesCount = 5;

  /**
   * {@inheritdoc}
   */
  protected function prepareSetUp() {
    $this->createMultipleTestField();
    $this->createRequiredTestField();
    parent::prepareSetUp();

    $this->originalEntityTitle = NULL;
    $this->changedEntityTitle = NULL;
  }

  /**
   * Tests autosave.
   */
  public function testAutosaveForms() {
    // It looks like phantomjs is crashing if an ajax autosave form request is
    // still running when we go the next test and visit the entity form of
    // different entity in which case the currently running ajax autosave form
    // request will be aborted. In order to prevent this crash we re-log the
    // user prior to each test.
    $this->doTestAutosaveFormNewEntity();

    $this->relogUser();
    $this->doTestAutosaveFormExistingEntity();

    $this->relogUser();
    $this->doTestSavingRestoredEntityForm();

    $this->relogUser();
    $this->doTestConcurrentEditing();

    $this->relogUser();
    $this->doTestAutosaveAfterFormValidationFail();

    $this->relogUser();
    $this->doTestAutosaveStatesPurgingOnConfigEvent();
  }

  /**
   * Tests that the autosave form library is not loaded on new entity forms.
   */
  protected function doTestAutosaveFormNewEntity() {
    $this->drupalGet($this->getCreateNewEntityURL());
    $this->assertAutosaveFormLibraryLoaded(FALSE);
  }

  /**
   * Tests the autosave support on entity forms.
   */
  protected function doTestAutosaveFormExistingEntity() {
    $entity = $this->createTestEntity();
    $entity_id = $entity->id();
    $entity_form_edit_url = $entity->toUrl('edit-form');

    $this->drupalGet($entity_form_edit_url);
    $this->assertAutosaveFormLibraryLoaded(TRUE);
    $this->assertOriginalEntityTitleAsPageTitle();

    // Wait for at least having two autosave submits being executed and assert
    // that with no changes there will be no autosave states created.
    $this->assertTrue($this->waitForAutosaveSubmits(2));
    $this->assertEquals(0, $this->getCountAutosaveEntries($entity_id));

    $latest_autosave_timestamp_per_change = $this->makeAllEntityFormChanges($entity_id);

    // Test the autosave restore of each change.
    for ($change_id = $this->testAutosaveFormExistingEntityChangesCount; $change_id > 0; $change_id--) {
      // Reload page otherwise new auto saves will be done and time checks get incorrect
      $this->drupalGet($entity_form_edit_url);
      if (($last_autosave_timestamp = $this->getLastAutosaveTimestamp($entity_id)) && ($last_autosave_timestamp > $latest_autosave_timestamp_per_change[$change_id])) {
        $delete_timestamps = range($latest_autosave_timestamp_per_change[$change_id] + 1, $last_autosave_timestamp);
        $this->deleteAutosavedStates($delete_timestamps);
      }

      $this->reloadPageAndRestore($entity_form_edit_url, $this->getLastAutosaveTimestamp($entity_id));
      $this->assertCorrectlyRestoredEntityFormState($change_id);
    }
  }

  /**
   * Tests saving an entity form restored from an autosaved state.
   */
  protected function doTestSavingRestoredEntityForm() {
    $entity = $this->createTestEntity();
    $entity_form_edit_url = $entity->toUrl('edit-form');

    $this->drupalGet($entity_form_edit_url);

    $this->makeAllEntityFormChanges($entity->id());

    // Assure that an autosave submission has run.
    $this->assertTrue($this->waitForAutosaveSubmits(1));

    $this->reloadPageAndRestore($entity_form_edit_url, $this->getLastAutosaveTimestamp($entity->id()));
    $this->saveForm();

    $this->finalizeTestSavingRestoredEntityForm($entity->id());
  }

  /**
   * Submits the current form.
   */
  protected function saveForm() {
    $this->drupalPostForm(NULL, [], t('Save'));
  }

  /**
   * Tests concurrent editing.
   */
  protected function doTestConcurrentEditing() {
    $entity = $this->createTestEntity();

    // This test supports only entities implementing the entity changed
    // interface.
    if (!($entity instanceof EntityChangedInterface)) {
      return;
    }

    // Make one change and assert that an autosave entry has been created for
    // it.
    $entity_form_edit_url = $entity->toUrl('edit-form');
    $this->drupalGet($entity_form_edit_url);
    $this->assertTrue($this->waitForAutosaveSubmits(1));
    $this->makeEntityFormChange(1);
    $this->assertTrue($this->waitForAutosaveSubmits(2));
    $this->assertTrue($this->getCountAutosaveEntries($entity->id()) > 0);
    $this->assertAutosaveIsRunning(TRUE);

    // Meanwhile simulate saving by another user in the background.
    $entity->setChangedTime($entity->getChangedTime() + 1)
      ->save();

    // Ensure that after the entity is being saved in the background the
    // autosave submission is disabled by expecting maximum of one autosave
    // submission which will show the alert message on the page. If when the
    // following code is executed the autosave submission has not yet run then
    // there will be one autosave submission and afterwards autosave submission
    // should be disabled. If however when the following code is executed the
    // autosave submission has already run then autosave submission should have
    // been disabled already. In both cases we assert that we expect zero or
    // one autosave submission, but not more than one.
    $this->assertFalse($this->waitForAutosaveSubmits(2));

    $this->assertAutosaveIsRunning(FALSE);
    $this->assertEquals(0, $this->getCountAutosaveEntries($entity->id()));

    $message = $this->config('autosave_form.messages')
      ->get('entity_saved_in_background_alert_message');
    $this->assertSession()->responseContains($message);
  }

  /**
   * Tests the autosave message not being shown on reload after validation fail.
   */
  protected function doTestAutosaveAfterFormValidationFail() {
    // Create a test entity and ensure that the required field is not filled in
    // order to trigger a validation error on entity form submission.
    $entity = $this->createTestEntity();

    // Disable the HTML5 validation as it prevents the form submission when a
    // required field is empty, which however we want to do on purpose to test
    // how autosave_form behaves when a form is returned with validation errors,
    // but for that it has to be submitted first.
    \Drupal::state()->set('disable_html5_validation', TRUE);

    $entity_form_edit_url = $entity->toUrl('edit-form');
    $this->drupalGet($entity_form_edit_url);

    // Assure that the initial autosave submission for gathering initial input
    // has run.
    $this->assertTrue($this->waitForAutosaveSubmits(1));

    // Make the first change to trigger an autosave state creation, but do not
    // fill the required field.
    $this->alterTitleField();
    // Ensure a validation fail will occur.
    $this->emptyRequiredFieldTestAutosaveAfterFormValidationFail();

    // Ensure an autosave state is saved.
    $this->assertTrue($this->waitForAutosaveSubmits(2));
    $before_submission_autosave_entries = $this->getCountAutosaveEntries($entity->id());
    $this->assertTrue($before_submission_autosave_entries > 0);

    // Submit the form.
    $this->saveForm();

    // Do not prevent the HTML5 validation anymore.
    \Drupal::state()->delete('disable_html5_validation');

    $this->logHtmlOutput(__FUNCTION__ . ' after validation fail.');

    // Ensure the validation fail message is shown.
    $error_messages = $this->getSession()->getPage()->find('css', '.messages--error');
    $this->assertNotNull($error_messages);

    // Ensure that the autosave resume/discard message is not shown.
    $this->assertAutosaveResumeDiscardMessageIsShown(FALSE, $this->getLastAutosaveTimestamp($entity->id()));

    // Ensure that autosave submissions are running.
    $this->assertTrue($this->waitForAutosaveSubmits(2));
    // Ensure no further auosave states are being created without changes.
    $this->assertEquals($before_submission_autosave_entries, $this->getCountAutosaveEntries($entity->id()));
  }

  /**
   * Tests that autosave states are purged on modifying a form related config.
   */
  protected function doTestAutosaveStatesPurgingOnConfigEvent() {
    $entity = $this->createTestEntity();
    $entity_id = $entity->id();
    $entity_form_edit_url = $entity->toUrl('edit-form');

    $create_autosave_state = function () use ($entity_form_edit_url, $entity_id) {
      $this->drupalGet($entity_form_edit_url);
      // Wait for at least having two autosave submits being executed, make a
      // change wait for two more autosave submits and ensure an autosave state
      // has been created.
      $this->assertTrue($this->waitForAutosaveSubmits(2));
      $this->makeEntityFormChange(1);
      $this->assertTrue($this->waitForAutosaveSubmits(2));
      $this->assertEquals(1, $this->getCountAutosaveEntries($entity_id));
    };

    // Make a non-significant modification on a form related config and ensure
    // that the autosave state hasn't been purged.
    $create_autosave_state();
    $field_config = FieldConfig::loadByName($this->entityType, $this->bundle, $this->unlimitedCardinalityField);
    $field_config->setLabel('New Label Test AutosaveState Purge')
      ->save();
    $this->assertEquals(1, $this->getCountAutosaveEntries($entity_id));

    // Modify a form related config and ensure that the autosave state has been
    // purged.
    $field_storage = FieldStorageConfig::loadByName($this->entityType, $this->unlimitedCardinalityField);
    $field_storage->setCardinality(10)
      ->save();
    $this->assertEquals(0, $this->getCountAutosaveEntries($entity_id));

    // Delete a form related config and ensure that the autosave state has been
    // purged.
    $create_autosave_state();
    $field_storage = FieldStorageConfig::loadByName($this->entityType, $this->unlimitedCardinalityField);
    $field_storage->delete();
    $this->assertEquals(0, $this->getCountAutosaveEntries($entity_id));

    // Create a form related config and ensure that the autosave state has been
    // purged.
    $create_autosave_state();
    $this->createMultipleTestField();
    $this->assertEquals(0, $this->getCountAutosaveEntries($entity_id));
  }

  /**
   * Empties a required field.
   *
   * Helper method for ::doTestAutosaveAfterFormValidationFail() to empty a
   * required field on the entity form in order to trigger a form validation
   * fail on form submission.
   */
  protected function emptyRequiredFieldTestAutosaveAfterFormValidationFail() {
    $this->fillTestField($this->requiredField, 0, '');
  }

  /**
   * Tests correctly saved entity after autosave restore.
   *
   * Helper method for ::doTestSavingRestoredEntityForm() to test the saved
   * entity.
   *
   * @param mixed $entity_id
   *   The ID of the entity.
   */
  protected function finalizeTestSavingRestoredEntityForm($entity_id) {
    $entity = $this->reloadEntity($entity_id);
    // Change 1.
    $this->assertEquals($entity->label(), $this->changedEntityTitle);
    // Changes 2, 3 and 4.
    $this->assertEquals(2, $entity->get($this->unlimitedCardinalityField)->count());
    $this->assertEquals('delta 0', $entity->get($this->unlimitedCardinalityField)->get(0)->value);
    $this->assertEquals('delta 1', $entity->get($this->unlimitedCardinalityField)->get(1)->value);
    // Change 5.
    $this->assertEquals('required test field', $entity->get($this->requiredField)->get(0)->value);
  }

  /**
   * Executes all change steps.
   *
   * @param mixed $entity_id
   *   The ID of the entity.
   *
   * @return array
   *   An array keyed by the change ID and having as value the latest autosave
   *   timestamp.
   */
  protected function makeAllEntityFormChanges($entity_id) {
    $this->logHtmlOutput(__FUNCTION__ . ' before changes are made');

    // Assure the first autosave submission for gathering the initial input has
    // been executed before making any changes, otherwise it might happen that
    // a change is made too fast and makes its way into the initial user input
    // used for comparison in order to determine if a new autosave state has
    // to be created or not.
    $this->assertTrue($this->waitForAutosaveSubmits(1));

    $latest_autosave_timestamp_per_change = [];

    for ($change_id = 1; $change_id <= $this->testAutosaveFormExistingEntityChangesCount; $change_id++) {
      $before_change_autosave_entries = $this->getCountAutosaveEntries($entity_id);

      $this->makeEntityFormChange($change_id);

      // Assert that a new autosave has been created, but wait for at least two
      // autosave submits to exclude any race conditions.
      $this->assertTrue($this->waitForAutosaveSubmits(2));
      $after_change_autosave_entries = $this->getCountAutosaveEntries($entity_id);
      $this->assertTrue($after_change_autosave_entries > $before_change_autosave_entries);

      // Wait for at least two more autosave submits to ensure no additional
      // autosave states are being created.
      $this->assertTrue($this->waitForAutosaveSubmits(2));
      $this->assertEquals($after_change_autosave_entries, $this->getCountAutosaveEntries($entity_id));

      $latest_autosave_timestamp_per_change[$change_id] = $this->getLastAutosaveTimestamp($entity_id);
    }

    $this->logHtmlOutput(__FUNCTION__ . ' after changes are made');

    return $latest_autosave_timestamp_per_change;
  }

  /**
   * Makes a change by the given change/step ID.
   *
   * @param $change_id
   *   The change id of the change to make.
   */
  protected function makeEntityFormChange($change_id) {
    $this->logHtmlOutput(__FUNCTION__ . ' before change ' . $change_id);

    switch ($change_id) {
      case 1:
        // Alter the title field.
        $this->alterTitleField();
        break;

      case 2:
        // Fill the first item of the test field and assert that a new autosave
        // entry has been created.
        $this->fillTestField($this->unlimitedCardinalityField, 0, 'delta 0');
        break;

      case 3:
        // Add new item to the test field and assert that a new autosave entry has
        // been created.
        $new_delta_expected = 1;
        $this->addItemToUnlimitedTestField($new_delta_expected);
        break;

      case 4:
        // Fill the new item of the test field and assert that a new autosave
        // entry has been created.
        $field_delta = 1;
        $this->fillTestField($this->unlimitedCardinalityField, $field_delta, 'delta 1');
        break;

      case 5:
        // Fill the required test field.
        $this->fillTestField($this->requiredField, 0, 'required test field');
        break;
    }

    $this->logHtmlOutput(__FUNCTION__ . ' after change ' . $change_id);
  }

  /**
   * Tests the restored autosave state by the change ID.
   *
   * @param $change_id
   *  The change ID for which to test the restored autosaved state.
   *
   * @see ::makeEntityFormChange().
   */
  protected function assertCorrectlyRestoredEntityFormState($change_id) {
    $page = $this->getSession()->getPage();

    $this->logHtmlOutput(__FUNCTION__ . ' before restore of change ' . $change_id);

    switch ($change_id) {
      case 5:
        $test_field_delta_0 = $page->findField($this->requiredField . '[0][value]');
        $this->assertNotEmpty($test_field_delta_0);
        $this->assertEquals('required test field', $test_field_delta_0->getValue());

      case 4:
        $test_field_delta_1 = $page->findField($this->unlimitedCardinalityField . '[1][value]');
        $this->assertNotEmpty($test_field_delta_1);
        $this->assertEquals('delta 1', $test_field_delta_1->getValue());

      case 3:
        // Not applying because of case 4.
        if ($change_id == 3) {
          $test_field_delta_1 = $page->findField($this->unlimitedCardinalityField . '[1][value]');
          $this->assertNotEmpty($test_field_delta_1);
          $this->assertEquals('', $test_field_delta_1->getValue());
        }

      case 2:
        $test_field_delta_0 = $page->findField($this->unlimitedCardinalityField . '[0][value]');
        $this->assertNotEmpty($test_field_delta_0);
        $this->assertEquals('delta 0', $test_field_delta_0->getValue());

      case 1:
        $this->assertOriginalEntityTitleAsPageTitle();
        $entity_type = \Drupal::entityTypeManager()->getDefinition($this->entityType);
        $this->assertEquals($this->changedEntityTitle, $page->findField($entity_type->getKey('label') . '[0][value]')->getValue());
        break;
    }

    $this->logHtmlOutput(__FUNCTION__ . ' after restore of change ' . $change_id);
  }

  /**
   * Alters the title field of the entity.
   *
   * @param string $changed_title
   *   The title to use to set on the title form field.
   */
  protected function alterTitleField($changed_title = 'changed title') {
    if ($label_field_name = \Drupal::entityTypeManager()->getDefinition($this->entityType)->getKey('label')) {
      $this->changedEntityTitle = $changed_title;
      $this->getSession()->getPage()->fillField($label_field_name . '[0][value]', $this->changedEntityTitle);
    }
  }

  /**
   * Fields a field item by its name and delta.
   *
   * @param $field_name
   *   The name of the field.
   * @param $delta
   *   The delta item of the field.
   * @param $value
   *   The value.
   */
  protected function fillTestField($field_name, $delta, $value) {
    $this->getSession()->getPage()->fillField("{$field_name}[{$delta}][value]", $value);
  }

  /**
   * Adds a new item to the unlimited test field.
   *
   * @param $delta
   *   The new excepted delta.
   */
  protected function addItemToUnlimitedTestField($delta) {
    $this->logHtmlOutput(__FUNCTION__ . ' before adding a new item to unlimited field');

    $page = $this->getSession()->getPage();
    $add_button = $page->find('css', '[data-drupal-selector="edit-' . Html::cleanCssIdentifier($this->unlimitedCardinalityField) . '-add-more"]');
    $this->assertTrue(!empty($add_button));
    $add_button->press();
    $result = $this->assertSession()->waitForElement('css', "[name=\"{$this->unlimitedCardinalityField}[{$delta}][value]\"]");
    $this->assertNotEmpty($result);

    $this->logHtmlOutput(__FUNCTION__ . ' after adding a new item to unlimited field');
  }

  /**
   * Returns the currently rendered page title.
   *
   * @return string|NULL
   *   The page title.
   */
  protected function getCurrentPageTitle() {
    $element = $this->getSession()->getPage()->find('css', '.page-title');
    $title = !empty($element) ? $element->getText() : NULL;
    return $title;
  }

  /**
   * Asserts that page title matches the original entity title.
   */
  protected function assertOriginalEntityTitleAsPageTitle() {
    $current_title = $this->getCurrentPageTitle();
    $this->assertTrue(strpos($current_title, $this->originalEntityTitle) !== FALSE);
  }

  /**
   * Gets the count of autosave states.
   *
   * @param mixed $entity_id
   *   The ID of the entity.
   *
   * @return int
   *   The count of autosave entries.
   */
  protected function getCountAutosaveEntries($entity_id) {
    $count = \Drupal::database()
      ->select(AutosaveEntityFormStorageInterface::AUTOSAVE_ENTITY_FORM_TABLE)
      ->condition('entity_id', $entity_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    return $count !== FALSE ? $count : 0;
  }

  /**
   * Gets the count of autosave unique session entries.
   *
   * @return int
   *   The count of autosave unique session entries.
   */
  protected function getCountAutosaveSessionEntries() {
    $count = \Drupal::database()
      ->select(AutosaveEntityFormStorageInterface::AUTOSAVE_ENTITY_FORM_TABLE)
      ->groupBy('form_session_id')
      ->countQuery()
      ->execute()
      ->fetchField();
    return $count !== FALSE ? $count : 0;
  }

  /**
   * Returns the timestamp of the last autosave entry for the given entity ID.
   *
   * @param mixed $entity_id
   *   The ID of the entity.
   *
   * @return int|null
   *   The timestamp of the last autosave entry.
   */
  protected function getLastAutosaveTimestamp($entity_id) {
    $timestmap = \Drupal::database()
      ->select(AutosaveEntityFormStorageInterface::AUTOSAVE_ENTITY_FORM_TABLE, 't')
      ->fields('t', ['timestamp'])
      ->condition('entity_id', $entity_id)
      ->orderBy('timestamp', 'DESC')
      ->execute()
      ->fetchField();
    return $timestmap !== FALSE ? $timestmap : NULL;
  }

  /**
   * Deletes the autosaved states.
   *
   * @param $timestamps
   *   (optional) If specified, only the autosaved states with the given
   *   timestamps will be deleted.
   */
  protected function deleteAutosavedStates(array $timestamps = NULL) {
    $query = \Drupal::database()
      ->delete(AutosaveEntityFormStorageInterface::AUTOSAVE_ENTITY_FORM_TABLE);
    if (isset($timestamps)) {
      $query->condition('timestamp', $timestamps, 'IN');
    }
    $query->execute();
  }

  /**
   * Creates a new test entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The newly created entity.
   */
  protected function createTestEntity() {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type =  $entity_type_manager->getDefinition($this->entityType);
    $storage = $entity_type_manager->getStorage($this->entityType);

    $values = ['type' => $this->bundle];
    if ($label_field_name = $entity_type->getKey('label')) {
      $values[$label_field_name] =  'original title';
    }
    $entity = $storage->create($values);

    if ($entity instanceof EntityOwnerInterface) {
      $entity->setOwner($this->webUser);
    }
    elseif ($entity_type->hasKey('uid')) {
      $entity->set('uid', $this->webUser->id());
    }

    $entity->save();

    $this->originalEntityTitle = $entity->label();

    return $entity;
  }

  /**
   * Returns the test entity
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   */
  protected function reloadEntity($id) {
    $storage = \Drupal::entityTypeManager()->getStorage($this->entityType);
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

  /**
   * Creates a multiple field to test ajax submits with.
   */
  protected function createMultipleTestField() {
    if (!FieldStorageConfig::loadByName($this->entityType, $this->unlimitedCardinalityField)) {
      // Create an unlimited cardinality field.
      FieldStorageConfig::create([
        'field_name' => $this->unlimitedCardinalityField,
        'entity_type' => $this->entityType,
        'type' => 'text',
        'cardinality' => -1,
      ])->save();
    }

    if (!FieldConfig::loadByName($this->entityType, $this->bundle, $this->unlimitedCardinalityField)) {
      // Attach an instance of the field to the content type.
      FieldConfig::create([
        'field_name' => $this->unlimitedCardinalityField,
        'entity_type' => $this->entityType,
        'bundle' => $this->bundle,
        'label' => $this->randomMachineName() . '_label',
      ])->save();
      $this->getEntityFormDisplay($this->entityType, $this->bundle, 'default')
        ->setComponent($this->unlimitedCardinalityField, [
          'type' => 'text_textfield',
        ])
        ->save();
    }
  }

  /**
   * Creates a required test field to test ajax submits with.
   */
  protected function createRequiredTestField() {
    if (!FieldStorageConfig::loadByName($this->entityType, $this->requiredField)) {
      // Create a required test field.
      FieldStorageConfig::create([
        'field_name' => $this->requiredField,
        'entity_type' => $this->entityType,
        'type' => 'text',
        'cardinality' => 1,
      ])->save();
    }

    if (!FieldConfig::loadByName($this->entityType, $this->bundle, $this->requiredField)) {
      // Attach an instance of the field to the content type.
      FieldConfig::create([
        'field_name' => $this->requiredField,
        'entity_type' => $this->entityType,
        'bundle' => $this->bundle,
        'label' => $this->requiredField,
        'required' => TRUE,
      ])->save();
      $this->getEntityFormDisplay($this->entityType, $this->bundle, 'default')
        ->setComponent($this->requiredField, [
          'type' => 'text_textfield',
        ])
        ->save();
    }
  }

  /**
   * Logs out and logs in the web user.
   */
  protected function relogUser() {
    $this->drupalLogout();
    $this->drupalLogin($this->webUser);
  }

  /**
   * Returns the URL for creating a new entity.
   *
   * @return string
   *   The url for creating a new entity.
   */
  protected abstract function getCreateNewEntityURL();

  /**
   * Returns the entity form display associated with a bundle and form mode.
   *
   * This is just a wrapper around
   * Drupal\Core\Entity\EntityDisplayRepository::getFormDisplay() for Drupal
   * versions >= 8.8, with a fallback to entity_get_form_display() for prior
   * Drupal versions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $form_mode
   *   The form mode.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The entity form display associated with the given form mode.
   *
   * @todo Remove this once Drupal 8.7 is no longer supported.
   */
  private function getEntityFormDisplay($entity_type, $bundle, $form_mode) {
    if (version_compare(\Drupal::VERSION, '8.8', '>=')) {
      return \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $bundle, $form_mode);
    }
    else {
      // Because this code only runs for older Drupal versions, we do not need
      // or want IDEs or the Upgrade Status module warning people about this
      // deprecated code usage. Setting the function name dynamically
      // circumvents those warnings.
      $function = 'entity_get_form_display';
      return $function($entity_type, $bundle, $form_mode);
    }
  }
}
