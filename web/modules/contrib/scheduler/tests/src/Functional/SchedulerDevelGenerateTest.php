<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the Scheduler interaction with Devel Generate module.
 *
 * @group scheduler
 * @group legacy
 * @todo Remove the 'legacy' tag when Devel no longer uses the deprecated
 * $published parameter for setPublished(), and does not use functions
 * drupal_set_message(), format_date() and db_query_range().
 */
class SchedulerDevelGenerateTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['devel_generate'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a user with devel permission. Only 'administer devel_generate' is
    // actually required for these tests, but the others are useful.
    // 'access content overview' is needed for /admin/content  (but it is empty)
    // 'access content' is required to actually see the content list data.
    // 'view scheduled content' is required for /admin/content/scheduled.
    $this->develUser = $this->drupalCreateUser([
      'administer devel_generate',
      'view scheduled content',
      'access content',
      'access content overview',
    ]);
  }

  /**
   * Helper function to count scheduled nodes and assert the expected number.
   *
   * @param string $type
   *   The machine-name for the content type to be checked.
   * @param string $field
   *   The field name to count, either 'publish_on' or 'unpublish_on'.
   * @param int $num_nodes
   *   The total number of nodes that should exist.
   * @param int $num_scheduled
   *   The number of those nodes which should be scheduled with a $field.
   * @param int $time_range
   *   Optional time range from the devel form. The generated scheduler dates
   *   should be in a range of +/- this value from the current time.
   */
  protected function countScheduledNodes($type, $field, $num_nodes, $num_scheduled, $time_range = NULL) {
    // Check that the expected number of nodes have been created.
    $count = $this->nodeStorage->getQuery()
      ->condition('type', $type)
      ->count()
      ->execute();
    $this->assertEquals($num_nodes, $count, sprintf('The expected number of %s is %s, found %s', $type, $num_nodes, $count));

    // Check that the expected number of nodes have been scheduled.
    $count = $this->nodeStorage->getQuery()
      ->condition('type', $type)
      ->exists($field)
      ->count()
      ->execute();
    $this->assertEquals($num_scheduled, $count, sprintf('The expected number of scheduled %s is %s, found %s', $field, $num_scheduled, $count));

    if (isset($time_range)) {
      // Define the minimum and maximum times that we expect the scheduled dates
      // to be within. REQUEST_TIME remains static for the duration of this test
      // but even though devel_generate also uses uses REQUEST_TIME this will
      // slowly creep forward during sucessive calls. Tests can fail incorrectly
      // for this reason, hence the best approximation is to use time() when
      // calculating the upper end of the range.
      $min = $this->requestTime - $time_range;
      $max = time() + $time_range;

      $query = $this->nodeStorage->getAggregateQuery();
      $result = $query
        ->condition('type', $type)
        ->aggregate($field, 'min')
        ->aggregate($field, 'max')
        ->execute();
      $min_found = $result[0]["{$field}_min"];
      $max_found = $result[0]["{$field}_max"];

      // Assert that the found values are within the expcted range.
      $this->assertGreaterThanOrEqual($min, $min_found, sprintf('The minimum value for %s is %s, smaller than the expected %s', $field, $this->dateFormatter->format($min_found, 'custom', 'j M, H:i:s'), $this->dateFormatter->format($min, 'custom', 'j M, H:i:s')));
      $this->assertLessThanOrEqual($max, $max_found, sprintf('The maximum value for %s is %s which is larger than expected %s', $field, $this->dateFormatter->format($max_found, 'custom', 'j M, H:i:s'), $this->dateFormatter->format($max, 'custom', 'j M, H:i:s')));
    }
  }

  /**
   * Test the functionality that Scheduler adds during content generation.
   */
  public function testDevelGenerate() {
    $this->drupalLogin($this->develUser);

    // Use the minimum required settings to see what happens when everything
    // else is left as default.
    $generate_settings = [
      "edit-node-types-$this->type" => TRUE,
    ];
    $this->drupalPostForm('admin/config/development/generate/content', $generate_settings, 'Generate');
    // Display the full content list and the scheduled list. Calls to these
    // pages are for information and debug only. They could be removed.
    $this->drupalGet('admin/content');
    $this->drupalGet('admin/content/scheduled');

    // Delete all content for this type and generate new content with only
    // publish-on dates. Use 100% as this is how we can count the expected
    // number of scheduled nodes. The time range of 3600 is one hour.
    // The number of nodes has to be lower than 50 until Devel issue with
    // undefined index 'users' is available and we switch to using 8.x-3.0
    // See https://www.drupal.org/project/devel/issues/3076613
    $generate_settings = [
      "edit-node-types-$this->type" => TRUE,
      'num' => 40,
      'kill' => TRUE,
      'time_range' => 3600,
      'scheduler_publishing' => 100,
      'scheduler_unpublishing' => 0,
    ];
    $this->drupalPostForm('admin/config/development/generate/content', $generate_settings, 'Generate');
    $this->drupalGet('admin/content');
    $this->drupalGet('admin/content/scheduled');

    // Check we have the expected number of nodes scheduled for publishing only
    // and verify that that the dates are within the time range specified.
    $this->countScheduledNodes($this->type, 'publish_on', 40, 40, $generate_settings['time_range']);
    $this->countScheduledNodes($this->type, 'unpublish_on', 40, 0);

    // Do similar for unpublish_on date. Delete all then generate new content
    // with only unpublish-on dates. Time range 86400 is one day.
    $generate_settings = [
      "edit-node-types-$this->type" => TRUE,
      'num' => 30,
      'kill' => TRUE,
      'time_range' => 86400,
      'scheduler_publishing' => 0,
      'scheduler_unpublishing' => 100,
    ];
    $this->drupalPostForm('admin/config/development/generate/content', $generate_settings, 'Generate');
    $this->drupalGet('admin/content');
    $this->drupalGet('admin/content/scheduled');

    // Check we have the expected number of nodes scheduled for unpublishing
    // only, and verify that that the dates are within the time range specified.
    $this->countScheduledNodes($this->type, 'publish_on', 30, 0);
    $this->countScheduledNodes($this->type, 'unpublish_on', 30, 30, $generate_settings['time_range']);

    // Generate new content using the type which is not enabled for Scheduler.
    // The nodes should be created but no dates should be added even though the
    // scheduler values are set to 100.
    $non_scheduler_id = $this->nonSchedulerNodeType->id();
    $generate_settings = [
      "edit-node-types-$non_scheduler_id" => TRUE,
      'num' => 20,
      'kill' => TRUE,
      'scheduler_publishing' => 100,
      'scheduler_unpublishing' => 100,
    ];
    $this->drupalPostForm('admin/config/development/generate/content', $generate_settings, 'Generate');
    $this->drupalGet('admin/content');
    $this->drupalGet('admin/content/scheduled');

    // Check we have the expected number of nodes but that none are scheduled.
    $this->countScheduledNodes($non_scheduler_id, 'publish_on', 20, 0);
    $this->countScheduledNodes($non_scheduler_id, 'unpublish_on', 20, 0);
  }

}
