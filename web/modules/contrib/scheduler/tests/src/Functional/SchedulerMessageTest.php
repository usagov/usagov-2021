<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the option to display or not display the confirmations message.
 *
 * @group scheduler
 */
class SchedulerMessageTest extends SchedulerBrowserTestBase {

  /**
   * Test the                .
   */
  public function testConfirmationMessage() {
    // Log in.
    $this->drupalLogin($this->schedulerUser);

    $publish_on = strtotime('+ 1 day 5 hours');
    $unpublish_on = strtotime('+ 2 day 7 hours');
    $publish_on_formatted = $this->dateFormatter->format($publish_on, 'long');
    $unpublish_on_formatted = $this->dateFormatter->format($unpublish_on, 'long');
    $title1 = 'Test 1 - ' . $this->randomString(10);
    $title2 = 'Test 2 - ' . $this->randomString(10);
    $title3 = 'Test 3 - ' . $this->randomString(10);

    // Create the content and check that the messages are shown by default.
    // First just a publish_on date.
    $edit = [
      'title[0][value]' => $title1,
      'publish_on[0][value][date]' => date('Y-m-d', $publish_on),
      'publish_on[0][value][time]' => date('H:i:s', $publish_on),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node1 = $this->drupalGetNodeByTitle($title1);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s', $title1, $publish_on_formatted));

    // Second, just an unpublish_on date.
    $edit = [
      'title[0][value]' => $title2,
      'unpublish_on[0][value][date]' => date('Y-m-d', $unpublish_on),
      'unpublish_on[0][value][time]' => date('H:i:s', $unpublish_on),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node2 = $this->drupalGetNodeByTitle($title2);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be unpublished %s', $title2, $unpublish_on_formatted));

    // Third, a node with both dates.
    $edit = [
      'title[0][value]' => $title3,
      'publish_on[0][value][date]' => date('Y-m-d', $publish_on),
      'publish_on[0][value][time]' => date('H:i:s', $publish_on),
      'unpublish_on[0][value][date]' => date('Y-m-d', $unpublish_on),
      'unpublish_on[0][value][time]' => date('H:i:s', $unpublish_on),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node3 = $this->drupalGetNodeByTitle($title3);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s', $title3, $publish_on_formatted, $unpublish_on_formatted));

    // Change the option to not display the messages.
    $this->nodetype->setThirdPartySetting('scheduler', 'show_message_after_update', FALSE)->save();
    $this->drupalPostForm('node/' . $node1->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be published');
    $this->drupalPostForm('node/' . $node2->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be unpublished');
    $this->drupalPostForm('node/' . $node3->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be published');

    // Set back to display the messages, and check after edit.
    $this->nodetype->setThirdPartySetting('scheduler', 'show_message_after_update', TRUE)->save();
    $this->drupalPostForm('node/' . $node1->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s', $title1, $publish_on_formatted));
    $this->drupalPostForm('node/' . $node2->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be unpublished %s', $title2, $unpublish_on_formatted));
    $this->drupalPostForm('node/' . $node3->id() . '/edit', [], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s', $title3, $publish_on_formatted, $unpublish_on_formatted));
  }

}
