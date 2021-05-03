<?php

namespace Drupal\Tests\content_lock\Functional;

/**
 * Content lock modes tests.
 *
 * @group content_lock
 */
class ContentLockModesTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'content_lock',
    'content_lock_test',
  ];

  /**
   * Test simultaneous edit on test entity.
   */
  public function testEnabledForSelectedFormModes() {
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
      'entity_test_mul_changed[settings][form_op_lock][mode]' => 1,
      'entity_test_mul_changed[settings][form_op_lock][values][default]' => 'default',
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));
    $lockService = \Drupal::service('content_lock');

    // Login user 1.
    $this->drupalLogin($this->user1);
    // Create lock on default form.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing');
    $this->assertNotFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'default', 'entity_test_mul_changed'));

    // Enter compact form mode without creating lock.
    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextNotContains('This content is now locked against simultaneous editing');
    $this->assertFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'compact', 'entity_test_mul_changed'));

    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('This content is being edited by the user');
    // Fields are disabled.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // Enter compact form mode without creating lock.
    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextNotContains('This content is now locked against simultaneous editing');
    $this->assertFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'compact', 'entity_test_mul_changed'));
    // Fields are open.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertFalse($input->hasAttribute('disabled'));

  }

  /**
   * Test simultaneous edit on test entity.
   */
  public function testDisabledForSelectedFormModes() {
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
      'entity_test_mul_changed[settings][form_op_lock][mode]' => 2,
      'entity_test_mul_changed[settings][form_op_lock][values][default]' => 'default',
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));
    $lockService = \Drupal::service('content_lock');

    // Login user 1.
    $this->drupalLogin($this->user1);
    // Enter default form mode without creating lock.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextNotContains('This content is now locked against simultaneous editing');
    $this->assertFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'default', 'entity_test_mul_changed'));

    // Create lock on compact form.
    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing');
    $this->assertNotFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'compact', 'entity_test_mul_changed'));

    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextNotContains('This content is being edited by the user');
    // Fields are open.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertFalse($input->hasAttribute('disabled'));

    // Enter compact and it's blocked.
    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextContains('This content is being edited by the user');
    $this->assertNotFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), 'compact', 'entity_test_mul_changed'));
    // Fields are disabled.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));
  }

  /**
   * Test that all forms are getting locked.
   */
  public function testMultipleFormModes() {
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));
    $lockService = \Drupal::service('content_lock');

    // Login user 1.
    $this->drupalLogin($this->user1);
    // Enter default form mode and create lock.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing');
    $this->assertNotFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), NULL, 'entity_test_mul_changed'));

    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextContains('This content is now locked by you against simultaneous editing');
    $this->assertNotFalse($lockService->fetchLock($this->entity->id(), $this->entity->language()->getId(), NULL, 'entity_test_mul_changed'));

    // Login as user 2. Shouldn't be able to edit forms.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('This content is being edited by the user');
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    $this->drupalGet($this->entity->toUrl('compact'));
    $this->assertSession()->pageTextContains('This content is being edited by the user');
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));
  }

}
