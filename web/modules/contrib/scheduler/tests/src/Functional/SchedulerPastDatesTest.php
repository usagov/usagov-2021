<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the options and processing when dates are entered in the past.
 *
 * @group scheduler
 */
class SchedulerPastDatesTest extends SchedulerBrowserTestBase {

  /**
   * Test the different options for past publication dates.
   */
  public function testSchedulerPastDates() {
    // Log in.
    $this->drupalLogin($this->schedulerUser);

    // Create an unpublished page node.
    /** @var NodeInterface $node */
    $node = $this->drupalCreateNode(['type' => $this->type, 'status' => FALSE]);
    $created_time = $node->getCreatedTime();

    // Test the default behavior: an error message should be shown when the user
    // enters a publication date that is in the past.
    $edit = [
      'title[0][value]' => 'Past ' . $this->randomString(10),
      'publish_on[0][value][date]' => $this->dateFormatter->format(strtotime('-1 day', $this->requestTime), 'custom', 'Y-m-d'),
      'publish_on[0][value][time]' => $this->dateFormatter->format(strtotime('-1 day', $this->requestTime), 'custom', 'H:i:s'),
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $this->assertSession()->pageTextContains("The 'publish on' date must be in the future");

    // Test the 'error' behavior explicitly.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'error')->save();
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $this->assertSession()->pageTextContains("The 'publish on' date must be in the future");

    // Test the 'publish' behavior: the node should be published immediately.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    // Check that no error message is shown when the publication date is in the
    // past and the "publish" behavior is chosen.
    $this->assertSession()->pageTextNotContains("The 'publish on' date must be in the future");
    $this->assertSession()->pageTextContains(sprintf('%s %s has been updated.', $this->typeName, $edit['title[0][value]']));

    // Reload the node.
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());

    // Check that the node is published and has the expected timestamps.
    $this->assertTrue($node->isPublished(), 'The node has been published immediately when the publication date is in the past and the "publish" behavior is chosen.');
    $this->assertNull($node->publish_on->value, 'The node publish_on date has been removed after publishing when the "publish" behavior is chosen.');
    $this->assertEquals($node->getChangedTime(), strtotime('-1 day', $this->requestTime), 'The changed time of the node has been updated to the publish_on time when published immediately.');
    $this->assertEquals($node->getCreatedTime(), $created_time, 'The created time of the node has not been changed when the "publish" behavior is chosen.');

    // Test the 'schedule' behavior: the node should be unpublished and become
    // published on the next cron run. Use a new unpublished node.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();
    $node = $this->drupalCreateNode(['type' => $this->type, 'status' => FALSE]);
    $created_time = $node->getCreatedTime();
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    // Check that no error is shown when the publish_on date is in the past.
    $this->assertSession()->pageTextNotContains("The 'publish on' date must be in the future");
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published', $edit['title[0][value]']));
    $this->assertSession()->pageTextContains(sprintf('%s %s has been updated.', $this->typeName, $edit['title[0][value]']));

    // Reload the node.
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());

    // Check that the node is unpublished but scheduled correctly.
    $this->assertFalse($node->isPublished(), 'The node has been unpublished when the publication date is in the past and the "schedule" behavior is chosen.');
    $this->assertEquals(strtotime('-1 day', $this->requestTime), (int) $node->publish_on->value, 'The node has the correct publish_on date stored.');

    // Simulate a cron run and check that the node is published.
    scheduler_cron();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertTrue($node->isPublished(), 'The node with publication date in the past and the "schedule" behavior has now been published by cron.');
    $this->assertEquals($node->getChangedTime(), strtotime('-1 day', $this->requestTime), 'The changed time of the node has been updated to the publish_on time when published via cron.');
    $this->assertEquals($node->getCreatedTime(), $created_time, 'The created time of the node has not been changed when the "schedule" behavior is chosen.');

    // Test the option to alter the creation time if the publishing time is
    // earlier than the node created time.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date_created', TRUE)->save();

    $past_date_options = [
      'publish' => 'publish',
      'schedule' => 'schedule',
    ];

    foreach ($past_date_options as $key => $option) {
      $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', $key)->save();

      // Create a new node, edit and save.
      $node = $this->drupalCreateNode(['type' => $this->type, 'status' => FALSE]);
      $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');

      if ($option == 'schedule') {
        scheduler_cron();
      }

      // Reload the node.
      $this->nodeStorage->resetCache([$node->id()]);
      $node = $this->nodeStorage->load($node->id());

      // Check that the created time has been altered to match publishing time.
      $this->assertEquals($node->getCreatedTime(), strtotime('-1 day', $this->requestTime), sprintf('The created time of the node has not been changed when the %s option is chosen.', $option));

    }

    // Check that an Unpublish date in the past fails validation.
    $edit = [
      'title[0][value]' => 'Unpublish in the past ' . $this->randomString(10),
      'unpublish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime - 3600, 'custom', 'Y-m-d'),
      'unpublish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime - 3600, 'custom', 'H:i:s'),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $this->assertSession()->pageTextContains("The 'unpublish on' date must be in the future");
  }

}
