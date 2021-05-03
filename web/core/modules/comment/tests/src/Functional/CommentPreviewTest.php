<?php

namespace Drupal\Tests\comment\Functional;

use Drupal\comment\CommentManagerInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\comment\Entity\Comment;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests comment preview.
 *
 * @group comment
 */
class CommentPreviewTest extends CommentTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * The profile to install as a basis for testing.
   *
   * Using the standard profile to test user picture display in comments.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Tests comment preview.
   */
  public function testCommentPreview() {
    // As admin user, configure comment settings.
    $this->drupalLogin($this->adminUser);
    $this->setCommentPreview(DRUPAL_OPTIONAL);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('default_mode', CommentManagerInterface::COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->drupalLogout();

    // Log in as web user.
    $this->drupalLogin($this->webUser);

    // Test escaping of the username on the preview form.
    \Drupal::service('module_installer')->install(['user_hooks_test']);
    \Drupal::state()->set('user_hooks_test_user_format_name_alter', TRUE);
    $edit = [];
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['comment_body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('node/' . $this->node->id(), $edit, 'Preview');
    $this->assertSession()->assertEscaped('<em>' . $this->webUser->id() . '</em>');

    \Drupal::state()->set('user_hooks_test_user_format_name_alter_safe', TRUE);
    $this->drupalPostForm('node/' . $this->node->id(), $edit, 'Preview');
    $this->assertInstanceOf(MarkupInterface::class, $this->webUser->getDisplayName());
    $this->assertSession()->assertNoEscaped('<em>' . $this->webUser->id() . '</em>');
    $this->assertRaw('<em>' . $this->webUser->id() . '</em>');

    // Add a user picture.
    $image = current($this->drupalGetTestFiles('image'));
    $user_edit['files[user_picture_0]'] = \Drupal::service('file_system')->realpath($image->uri);
    $this->drupalPostForm('user/' . $this->webUser->id() . '/edit', $user_edit, 'Save');

    // As the web user, fill in the comment form and preview the comment.
    $this->drupalPostForm('node/' . $this->node->id(), $edit, 'Preview');

    // Check that the preview is displaying the title and body.
    $this->assertSession()->titleEquals('Preview comment | Drupal');
    $this->assertText($edit['subject[0][value]']);
    $this->assertText($edit['comment_body[0][value]']);

    // Check that the title and body fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('comment_body[0][value]', $edit['comment_body[0][value]']);

    // Check that the user picture is displayed.
    $this->assertSession()->elementExists('xpath', "//article[contains(@class, 'preview')]//div[contains(@class, 'user-picture')]//img");
  }

  /**
   * Tests comment preview.
   */
  public function testCommentPreviewDuplicateSubmission() {
    // As admin user, configure comment settings.
    $this->drupalLogin($this->adminUser);
    $this->setCommentPreview(DRUPAL_OPTIONAL);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('default_mode', CommentManagerInterface::COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->drupalLogout();

    // Log in as web user.
    $this->drupalLogin($this->webUser);

    // As the web user, fill in the comment form and preview the comment.
    $edit = [];
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['comment_body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('node/' . $this->node->id(), $edit, 'Preview');

    // Check that the preview is displaying the title and body.
    $this->assertSession()->titleEquals('Preview comment | Drupal');
    $this->assertText($edit['subject[0][value]']);
    $this->assertText($edit['comment_body[0][value]']);

    // Check that the title and body fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('comment_body[0][value]', $edit['comment_body[0][value]']);

    // Store the content of this page.
    $this->submitForm([], 'Save');
    $this->assertText('Your comment has been posted.');
    $elements = $this->xpath('//section[contains(@class, "comment-wrapper")]/article');
    $this->assertCount(1, $elements);

    // Go back and re-submit the form.
    $this->getSession()->getDriver()->back();
    $submit_button = $this->assertSession()->buttonExists('Save');
    $submit_button->click();
    $this->assertText('Your comment has been posted.');
    $elements = $this->xpath('//section[contains(@class, "comment-wrapper")]/article');
    $this->assertCount(2, $elements);
  }

  /**
   * Tests comment edit, preview, and save.
   */
  public function testCommentEditPreviewSave() {
    $web_user = $this->drupalCreateUser([
      'access comments',
      'post comments',
      'skip comment approval',
      'edit own comments',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->setCommentPreview(DRUPAL_OPTIONAL);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('default_mode', CommentManagerInterface::COMMENT_MODE_THREADED, 'Comment paging changed.');

    $edit = [];
    $date = new DrupalDateTime('2008-03-02 17:23');
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['comment_body[0][value]'] = $this->randomMachineName(16);
    $edit['uid'] = $web_user->getAccountName() . ' (' . $web_user->id() . ')';
    $edit['date[date]'] = $date->format('Y-m-d');
    $edit['date[time]'] = $date->format('H:i:s');
    $raw_date = $date->getTimestamp();
    $expected_text_date = $this->container->get('date.formatter')->format($raw_date);
    $expected_form_date = $date->format('Y-m-d');
    $expected_form_time = $date->format('H:i:s');
    $comment = $this->postComment($this->node, $edit['subject[0][value]'], $edit['comment_body[0][value]'], TRUE);
    $this->drupalPostForm('comment/' . $comment->id() . '/edit', $edit, 'Preview');

    // Check that the preview is displaying the subject, comment, author and date correctly.
    $this->assertSession()->titleEquals('Preview comment | Drupal');
    $this->assertText($edit['subject[0][value]']);
    $this->assertText($edit['comment_body[0][value]']);
    $this->assertText($web_user->getAccountName());
    $this->assertText($expected_text_date);

    // Check that the subject, comment, author and date fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('comment_body[0][value]', $edit['comment_body[0][value]']);
    $this->assertSession()->fieldValueEquals('uid', $edit['uid']);
    $this->assertSession()->fieldValueEquals('date[date]', $edit['date[date]']);
    $this->assertSession()->fieldValueEquals('date[time]', $edit['date[time]']);

    // Check that saving a comment produces a success message.
    $this->drupalPostForm('comment/' . $comment->id() . '/edit', $edit, 'Save');
    $this->assertText('Your comment has been posted.');

    // Check that the comment fields are correct after loading the saved comment.
    $this->drupalGet('comment/' . $comment->id() . '/edit');
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('comment_body[0][value]', $edit['comment_body[0][value]']);
    $this->assertSession()->fieldValueEquals('uid', $edit['uid']);
    $this->assertSession()->fieldValueEquals('date[date]', $expected_form_date);
    $this->assertSession()->fieldValueEquals('date[time]', $expected_form_time);

    // Submit the form using the displayed values.
    $displayed = [];
    $displayed['subject[0][value]'] = current($this->xpath("//input[@id='edit-subject-0-value']"))->getValue();
    $displayed['comment_body[0][value]'] = current($this->xpath("//textarea[@id='edit-comment-body-0-value']"))->getValue();
    $displayed['uid'] = current($this->xpath("//input[@id='edit-uid']"))->getValue();
    $displayed['date[date]'] = current($this->xpath("//input[@id='edit-date-date']"))->getValue();
    $displayed['date[time]'] = current($this->xpath("//input[@id='edit-date-time']"))->getValue();
    $this->drupalPostForm('comment/' . $comment->id() . '/edit', $displayed, 'Save');

    // Check that the saved comment is still correct.
    $comment_storage = \Drupal::entityTypeManager()->getStorage('comment');
    $comment_storage->resetCache([$comment->id()]);
    /** @var \Drupal\comment\CommentInterface $comment_loaded */
    $comment_loaded = Comment::load($comment->id());
    $this->assertEqual($edit['subject[0][value]'], $comment_loaded->getSubject(), 'Subject loaded.');
    $this->assertEqual($edit['comment_body[0][value]'], $comment_loaded->comment_body->value, 'Comment body loaded.');
    $this->assertEqual($web_user->id(), $comment_loaded->getOwner()->id(), 'Name loaded.');
    $this->assertEqual($raw_date, $comment_loaded->getCreatedTime(), 'Date loaded.');
    $this->drupalLogout();

    // Check that the date and time of the comment are correct when edited by
    // non-admin users.
    $user_edit = [];
    $expected_created_time = $comment_loaded->getCreatedTime();
    $this->drupalLogin($web_user);
    // Web user cannot change the comment author.
    unset($edit['uid']);
    $this->drupalPostForm('comment/' . $comment->id() . '/edit', $user_edit, 'Save');
    $comment_storage->resetCache([$comment->id()]);
    $comment_loaded = Comment::load($comment->id());
    $this->assertEqual($expected_created_time, $comment_loaded->getCreatedTime(), 'Expected date and time for comment edited.');
    $this->drupalLogout();
  }

}
