<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests access to the scheduled content overview page and user tab.
 *
 * @group scheduler
 */
class SchedulerScheduledContentListAccessTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $base_permissions = [
      'access content',
      'create ' . $this->type . ' content',
      'view own unpublished content',
    ];

    $this->editorUser = $this->drupalCreateUser(array_merge($base_permissions, ['access content overview']));
    $this->schedulerUser = $this->drupalCreateUser(array_merge($base_permissions, ['schedule publishing of nodes']));
    $this->schedulerManager = $this->drupalCreateUser(array_merge($base_permissions, ['view scheduled content']));

    // Create nodes scheduled for publishing and for unpublishing.
    $this->node1 = $this->drupalCreateNode([
      'title' => 'Node created by Scheduler User for publishing',
      'uid' => $this->schedulerUser->id(),
      'status' => FALSE,
      'type' => $this->type,
      'publish_on' => strtotime('+1 week'),
    ]);
    $this->node2 = $this->drupalCreateNode([
      'title' => 'Node created by Scheduler User for unpublishing',
      'uid' => $this->schedulerUser->id(),
      'status' => TRUE,
      'type' => $this->type,
      'unpublish_on' => strtotime('+1 week'),
    ]);
    $this->node3 = $this->drupalCreateNode([
      'title' => 'Node created by Scheduler Manager for publishing',
      'uid' => $this->schedulerManager->id(),
      'status' => FALSE,
      'type' => $this->type,
      'publish_on' => strtotime('+1 week'),
    ]);
    $this->node4 = $this->drupalCreateNode([
      'title' => 'Node created by Scheduler Manager for unpublishing',
      'uid' => $this->schedulerManager->id(),
      'status' => TRUE,
      'type' => $this->type,
      'unpublish_on' => strtotime('+1 week'),
    ]);
  }

  /**
   * Tests the scheduled content tab on the user page.
   */
  public function testViewScheduledContentUser() {
    $assert = $this->assertSession();

    // Access a scheduled content user tab as an anonymous visitor.
    $this->drupalGet("user/{$this->schedulerUser->id()}/scheduled");
    // An anonymous visitor cannot access a user's scheduled content tab.
    $assert->statusCodeEquals(403);

    // Try to access a users own scheduled content tab when they do not have
    // any scheduler permissions. This should give "403 Access Denied".
    $this->drupalLogin($this->editorUser);
    $this->drupalGet("user/{$this->editorUser->id()}/scheduled");
    $assert->statusCodeEquals(403);

    // Access a users own scheduled content tab when they have only
    // 'schedule publishing of nodes' permission. This will give "200 OK".
    $this->drupalLogin($this->schedulerUser);
    $this->drupalGet("user/{$this->schedulerUser->id()}/scheduled");
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Node created by Scheduler User for publishing');
    $assert->pageTextContains('Node created by Scheduler User for unpublishing');
    $assert->pageTextNotContains('Node created by Scheduler Manager for unpublishing');

    // Access another users scheduled content tab as "Scheduler User". This
    // should not be possible and will give "403 Access Denied".
    $this->drupalGet("user/{$this->schedulerManager->id()}/scheduled");
    $assert->statusCodeEquals(403);

    // Access the users own scheduled content tab as "Scheduler Manager" with
    // only 'view scheduled content' permission.
    $this->drupalLogin($this->schedulerManager);
    $this->drupalGet("user/{$this->schedulerManager->id()}/scheduled");
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Node created by Scheduler Manager for publishing');
    $assert->pageTextContains('Node created by Scheduler Manager for unpublishing');
    $assert->pageTextNotContains('Node created by Scheduler User for unpublishing');

    // Access another users scheduled content tab as "Scheduler Manager".
    // The published and unpublished content should be listed.
    $this->drupalGet("user/{$this->schedulerUser->id()}/scheduled");
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Node created by Scheduler User for publishing');
    $assert->pageTextContains('Node created by Scheduler User for unpublishing');
  }

  /**
   * Tests the scheduled content overview.
   */
  public function testViewScheduledContentOverview() {
    $assert = $this->assertSession();

    // Access the scheduled content overview as anonymous visitor.
    $this->drupalGet('admin/content/scheduled');
    $assert->statusCodeEquals(403);

    // Access the scheduled content overview as "Editor" without any
    // scheduler permissions.
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('admin/content/scheduled');
    $assert->statusCodeEquals(403);

    // Access the scheduled content overview as "Scheduler User" with only
    // 'schedule publishing of nodes' permission.
    $this->drupalLogin($this->schedulerUser);
    $this->drupalGet('admin/content/scheduled');
    $assert->statusCodeEquals(403);

    // Access the scheduled content overview as "Scheduler Manager" with only
    // 'view scheduled content' permission. They should be able to see the
    // scheduled published and unpublished content by all users.
    $this->drupalLogin($this->schedulerManager);
    $this->drupalGet('admin/content/scheduled');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Node created by Scheduler User for publishing');
    $assert->pageTextContains('Node created by Scheduler User for unpublishing');
    $assert->pageTextContains('Node created by Scheduler Manager for publishing');
    $assert->pageTextContains('Node created by Scheduler Manager for unpublishing');
  }

}
