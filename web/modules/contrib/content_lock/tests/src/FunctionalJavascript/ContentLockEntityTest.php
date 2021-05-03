<?php

namespace Drupal\Tests\content_lock\FunctionalJavascript;

/**
 * Class ContentLockEntityTest.
 *
 * @group content_lock
 */
class ContentLockEntityTest extends ContentLockJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test JS locking.
   */
  public function testJsLocking() {
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/config/content/content_lock');
    $this->click('#edit-entity-types-entity-test-mul-changed');
    $this->click('#edit-entity-test-mul-changed-settings-js-lock');
    $page->pressButton('Save configuration');

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user1->getDisplayName(),
    ]));
    $this->htmlOutput();
    $assert_session->linkExists(t('Break lock'));
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');
    // Fields are disabled.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // We save entity 1 and unlock it.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $page->pressButton(t('Save'));

    // We lock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user2->getDisplayName(),
    ]));
    $assert_session->linkNotExists(t('Break lock'));
    // Ensure the input is disabled.
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $page->pressButton(t('Save'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains(t('updated.'));
  }

}
