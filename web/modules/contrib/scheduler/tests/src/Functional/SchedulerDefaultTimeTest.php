<?php

namespace Drupal\Tests\scheduler\Functional;

use DateTime;
use DateInterval;

/**
 * Tests the default time functionality.
 *
 * @group scheduler
 */
class SchedulerDefaultTimeTest extends SchedulerBrowserTestBase {

  /**
   * Test the default time functionality during content creation and edit.
   *
   * This test covers the default scenario where the dates are optional and not
   * required. A javascript test covers the cases where the dates are required.
   */
  public function testDefaultTime() {
    $this->drupalLogin($this->schedulerUser);
    $config = $this->config('scheduler.settings');

    // For this test we use a default time of 6:30am.
    $default_time = '06:30:00';
    $config->set('default_time', $default_time)->save();

    // Create DateTime objects to hold the two scheduling dates. This is better
    // than using raw unix timestamps because it caters for daylight-saving
    // shifts properly.
    // @see https://www.drupal.org/project/scheduler/issues/2957490
    $publish_time = new DateTime();
    $publish_time->add(new DateInterval('P1D'))->setTime(6, 30);

    $unpublish_time = new DateTime();
    $unpublish_time->add(new DateInterval('P2D'))->setTime(6, 30);

    // We cannot easily test the full validation message as they contain the
    // current time which can be one or two seconds in the past. The best we can
    // do is check the fixed part of the message as it is when passed to t() in
    // Datetime::validateDatetime. Tests only needs to work in English anyway.
    $publish_validation_message = 'The Publish on date is invalid.';
    $unpublish_validation_message = 'The Unpublish on date is invalid.';

    // First test with the "date only" functionality disabled.
    $config->set('allow_date_only', FALSE)->save();

    // Test that entering a time is required.
    $edit = [
      'title[0][value]' => 'No time ' . $this->randomString(15),
      'publish_on[0][value][date]' => $publish_time->format('Y-m-d'),
      'unpublish_on[0][value][date]' => $unpublish_time->format('Y-m-d'),
    ];
    // Create a node and check that the expected error messages are shown.
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    // By default it is required to enter a time when scheduling content for
    // publishing and for unpublishing.
    $this->assertSession()->pageTextContains($publish_validation_message);
    $this->assertSession()->pageTextContains($unpublish_validation_message);

    // Allow the user to enter only a date with no time.
    $config->set('allow_date_only', TRUE)->save();

    // Create a node and check that the validation messages are not shown.
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $this->assertSession()->pageTextNotContains($publish_validation_message);
    $this->assertSession()->pageTextNotContains($unpublish_validation_message);

    // Get the pattern of the 'long' default date format.
    $date_format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');
    $long_pattern = $date_format_storage->load('long')->getPattern();

    // Check that the scheduled information is shown after saving.
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s',
      $edit['title[0][value]'], $publish_time->format($long_pattern), $unpublish_time->format($long_pattern)));

    // Protect this section in case the node was not created.
    if ($node = $this->drupalGetNodeByTitle($edit['title[0][value]'])) {
      // Check that the correct scheduled dates are stored in the node.
      $this->assertEquals($publish_time->getTimestamp(), (int) $node->publish_on->value, 'The node publish_on value is stored correctly.');
      $this->assertEquals($unpublish_time->getTimestamp(), (int) $node->unpublish_on->value, 'The node unpublish_on value is stored correctly.');

      // Check that the default time has been added to the form on edit.
      $this->drupalGet('node/' . $node->id() . '/edit');
      $this->assertFieldByName('publish_on[0][value][time]', $default_time, 'The default time offset has been added to the date field when scheduling content for publication.');
      $this->assertFieldByName('unpublish_on[0][value][time]', $default_time, 'The default time offset has been added to the date field when scheduling content for unpublication.');

    }
    else {
      $this->fail('The expected node was not found.');
    }
  }

}
