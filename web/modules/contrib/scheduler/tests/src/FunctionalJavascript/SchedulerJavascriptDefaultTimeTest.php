<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

use DateTime;
use DateInterval;

/**
 * Tests the JavaScript functionality for default dates.
 *
 * @group scheduler
 */
class SchedulerJavascriptDefaultTimeTest extends SchedulerJavascriptTestBase {

  /**
   * The HTML5 datepicker format.
   *
   * @var string
   */
  private $datepickerFormat;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Determine whether the HTML5 date picker is expecting d/m/Y or m/d/Y
    // because this varies with the locale and cannot be set or predetermined
    // using the site timezone. This is a bit of hack but it is necessary due
    // to local testing having a different locale to drupal.org testing.
    // @see https://www.drupal.org/project/scheduler/issues/2913829 from #18.
    $this->drupalLogin($this->schedulerUser);
    $this->drupalGet('node/add/' . $this->type);
    $page = $this->getSession()->getPage();
    $title = 'Date format test ' . $this->randomString(12);
    $page->fillField('edit-title-0-value', $title);
    $page->clickLink('Scheduling options');
    // Set the date using a day and month which could be correctly interpreted
    // either way. Set the year to be next year to ensure a future date.
    // Use a time format which includes 'pm' as this may be necessary, and will
    // be ignored if the time widget wants hh:mm:ss in 24 hours format.
    $page->fillField('edit-publish-on-0-value-date', '05/02/' . (date('Y') + 1));
    $page->fillField('edit-publish-on-0-value-time', '06:00:00pm');
    $page->pressButton('Save');
    $node = $this->drupalGetNodeByTitle($title);
    // If the saved month is 2 then the format is d/m/Y, otherwise it is m/d/Y.
    $this->datepickerFormat = (date('n', $node->publish_on->value) == 2 ? 'd/m/Y' : 'm/d/Y');
  }

  /**
   * Test the default time functionality when scheduling dates are required.
   *
   * @dataProvider dataDefaultTimeWhenSchedulingIsRequired()
   */
  public function testDefaultTimeWhenSchedulingIsRequired($field) {
    $config = $this->config('scheduler.settings');

    // This test is only relevant when the configuration allows a date only with
    // a default time specified. Testing with 'allow_date_only' = false is
    // covered in the browser test SchedulerDefaultTimeTest.
    $config->set('allow_date_only', TRUE)->save();

    // Use a default time of 19:30 (7:30pm).
    $default_time = '19:30:00';
    $config->set('default_time', $default_time)->save();

    // Create a DateTime object to hold the scheduling date. This is better than
    // using a raw unix timestamp because it caters for daylight-saving.
    $scheduling_time = new DateTime();
    $scheduling_time->add(new DateInterval('P1D'))->setTime(19, 30);

    foreach ([TRUE, FALSE] as $required) {
      // Set the publish-on/unpublish-on date to the $required setting.
      $this->nodetype->setThirdPartySetting('scheduler', $field . '_required', $required)->save();

      // Create a node.
      $this->drupalGet('node/add/' . $this->type);
      $page = $this->getSession()->getPage();

      $title = ucfirst($field) . ($required ? ' required ' : ' not required ') . $this->randomString(12);
      $page->fillField('edit-title-0-value', $title);
      $page->fillField('edit-body-0-value', 'datepickerFormat = ' . $this->datepickerFormat);
      $page->clickLink('Scheduling options');
      if ($required) {
        // Fill in the date value but do nothing with the time field.
        $page->fillField('edit-' . $field . '-on-0-value-date', $scheduling_time->format($this->datepickerFormat));
      }
      $page->pressButton('Save');

      // Test that the content has saved properly.
      $this->assertSession()->pageTextContains(sprintf('%s %s has been created', $this->typeName, $title));

      $node = $this->drupalGetNodeByTitle($title);
      $this->assertNotEmpty($node, 'The node could not be found');
      if ($required) {
        // Check that the scheduled date and time are correct.
        $this->assertEquals($scheduling_time->getTimestamp(), (int) $node->{$field . '_on'}->value);
      }
      else {
        // Check that no scheduled date was stored.
        $this->assertEmpty($node->{$field . '_on'}->value);
      }
    }
  }

  /**
   * Provides data for testDefaultTimeWhenSchedulingIsRequired().
   *
   * @return array
   *   The test data.
   */
  public function dataDefaultTimeWhenSchedulingIsRequired() {
    return [
      ['publish'],
      ['unpublish'],
    ];
  }

}
