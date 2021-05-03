<?php

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Node tests.
 *
 * @group content_lock
 */
class ContentLockNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'content_lock',
  ];

  /**
   * Test simultaneous edit on content type article.
   */
  public function testContentLockNode() {

    $this->drupalCreateContentType(['type' => 'article']);
    $article = $this->drupalCreateNode(['type' => 'article', 'title' => 'Article 1']);

    $admin = $this->drupalCreateUser([
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer content lock',
    ]);

    $user1 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
    ]);
    $user2 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'break content lock',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));

    // We lock article1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit article1.
    $this->drupalLogin($user2);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $user1->getDisplayName(),
    ]));
    $assert_session->linkExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));
    $textarea = $assert_session->elementExists('css', 'textarea#edit-body-0-value');
    $this->assertTrue($textarea->hasAttribute('disabled'));

    // We save article 1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm('/node/' . $article->id() . '/edit', [], t('Save'));

    // We lock article1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit article1.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $user2->getDisplayName(),
    ]));
    $assert_session->linkNotExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock article1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm('/node/' . $article->id() . '/edit', [], t('Save'));
    $assert_session->pageTextContains(t('updated.'));

  }

}
