<?php

namespace Drupal\Tests\content_lock\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\BrowserTestBase;

/**
 * Block tests.
 *
 * @group content_lock
 */
class ContentLockBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'content_lock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a custom block.
   *
   * @param bool|string $title
   *   (optional) Title of block. When no value is given uses a random name.
   *   Defaults to FALSE.
   * @param string $bundle
   *   (optional) Bundle name. Defaults to 'basic'.
   * @param bool $save
   *   (optional) Whether to save the block. Defaults to TRUE.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created custom block.
   */
  protected function createBlockContent($title = FALSE, $bundle = 'basic', $save = TRUE) {
    $title = $title ?: $this->randomMachineName();
    $block_content = BlockContent::create([
      'info' => $title,
      'type' => $bundle,
      'langcode' => 'en',
    ]);
    if ($block_content && $save === TRUE) {
      $block_content->save();
    }
    return $block_content;
  }

  /**
   * Creates a custom block type (bundle).
   *
   * @param string $label
   *   The block type label.
   * @param bool $create_body
   *   Whether or not to create the body field.
   *
   * @return \Drupal\block_content\Entity\BlockContentType
   *   Created custom block type.
   */
  protected function createBlockContentType($label, $create_body = FALSE) {
    $bundle = BlockContentType::create([
      'id' => $label,
      'label' => $label,
      'revision' => FALSE,
    ]);
    $bundle->save();
    if ($create_body) {
      block_content_add_body_field($bundle->id());
    }
    return $bundle;
  }

  /**
   * Test simultaneous edit on block.
   */
  public function testContentLockBlock() {

    // Create block.
    $this->createBlockContentType('basic', TRUE);
    $block1 = $this->createBlockContent('Block 1');

    $admin = $this->drupalCreateUser([
      'administer blocks',
      'administer content lock',
    ]);

    $user1 = $this->drupalCreateUser([
      'administer blocks',
      'access content',
    ]);
    $user2 = $this->drupalCreateUser([
      'administer blocks',
      'break content lock',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'block_content[bundles][basic]' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));

    // We lock block1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("block/{$block1->id()}");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit block1.
    $this->drupalLogin($user2);
    $this->drupalGet("block/{$block1->id()}");
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $user1->getDisplayName(),
    ]));
    $assert_session->linkExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We save block1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("block/{$block1->id()}");
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm('/block/' . $block1->id(), [], t('Save'));

    // We lock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("block/{$block1->id()}");
    $assert_session->pageTextContains(t('This content is now locked against simultaneous editing.'));

    // Other user can not edit block1.
    $this->drupalLogin($user1);
    $this->drupalGet("block/{$block1->id()}");
    $assert_session->pageTextContains(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $user2->getDisplayName(),
    ]));
    $assert_session->linkNotExists(t('Break lock'));
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("block/{$block1->id()}");
    $assert_session->pageTextContains(t('This content is now locked by you against simultaneous editing.'));
    $this->drupalPostForm('/block/' . $block1->id(), [], t('Save'));
    $assert_session->pageTextContains(t('has been updated.'));
  }

}
