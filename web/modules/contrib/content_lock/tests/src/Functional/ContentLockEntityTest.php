<?php

namespace Drupal\Tests\content_lock\Functional;

/**
 * Content lock entity tests.
 *
 * @group content_lock
 */
class ContentLockEntityTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test simultaneous edit on test entity.
   */
  public function testContentLockEntity() {

    // We protect the bundle created.
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user1->getDisplayName(),
    ]));
    $assert_session->linkExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // We save entity 1 and unlock it.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm($this->entity->toUrl('edit-form'), [], t('Save'));
    $assert_session->pageTextNotContains(t('against simultaneous editing.'));

    // We lock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user2->getDisplayName(),
    ]));
    $assert_session->linkNotExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm($this->entity->toUrl('edit-form'), [], t('Save'));
    $assert_session->pageTextContains(t('updated.'));
    $assert_session->pageTextNotContains(t('against simultaneous editing.'));
  }

}
