<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\rules\Context\ContextConfig;

/**
 * Tests the six events that Scheduler provides for use in Rules module.
 *
 * @group scheduler
 * @group legacy
 * @todo Remove the 'legacy' tag when Rules no longer uses deprecated code.
 * @see https://www.drupal.org/project/scheduler/issues/2924353
 */
class SchedulerRulesEventsTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['scheduler_rules_integration'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->rulesStorage = $this->container->get('entity_type.manager')->getStorage('rules_reaction_rule');
    $this->expressionManager = $this->container->get('plugin.manager.rules_expression');

  }

  /**
   * Tests the six events provided by Scheduler.
   *
   * This class tests all six events provided by Scheduler, by creating six
   * rules which are all active throughout the test. They are all checked in
   * this one test class to make the tests stronger, as this will show not only
   * that the correct events are triggered in the right places, but also
   * that they are not triggered in the wrong places.
   */
  public function testRulesEvents() {

    // Create six reaction rules, one for each event that Scheduler triggers.
    $rule_data = [
      1 => ['scheduler_new_node_is_scheduled_for_publishing_event', 'A new node is created and is scheduled for publishing.'],
      2 => ['scheduler_existing_node_is_scheduled_for_publishing_event', 'An existing node is saved and is scheduled for publishing.'],
      3 => ['scheduler_has_published_this_node_event', 'Scheduler has published this node during cron.'],
      4 => ['scheduler_new_node_is_scheduled_for_unpublishing_event', 'A new node is created and is scheduled for unpublishing.'],
      5 => ['scheduler_existing_node_is_scheduled_for_unpublishing_event', 'An existing node is saved and is scheduled for unpublishing.'],
      6 => ['scheduler_has_unpublished_this_node_event', 'Scheduler has unpublished this node during cron.'],
    ];
    // PHPCS throws a false-positive 'variable $var is undefined' message when
    // the variable is defined by list( ) syntax. To avoid the unwanted warnings
    // we can wrap the section with @codingStandardsIgnoreStart and IgnoreEnd.
    // @see https://www.drupal.org/project/coder/issues/2876245
    // @codingStandardsIgnoreStart
    foreach ($rule_data as $i => list($event_name, $description)) {
      $rule[$i] = $this->expressionManager->createRule();
      $message[$i] = 'RULES message ' . $i . '. ' . $description;
      $rule[$i]->addAction('rules_system_message', ContextConfig::create()
        ->setValue('message', $message[$i])
        ->setValue('type', 'status')
        );
      $config_entity = $this->rulesStorage->create([
        'id' => 'rule' . $i,
        'events' => [['event_name' => $event_name]],
        'expression' => $rule[$i]->getConfiguration(),
      ]);
      $config_entity->save();
    }
    // @codingStandardsIgnoreEnd

    $this->drupalLogin($this->schedulerUser);

    $assert = $this->assertSession();

    // Create a node without any scheduled dates, using node/add/ not
    // drupalCreateNode(), and check that no events are triggered.
    $edit = [
      'title[0][value]' => 'Test for no events',
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Edit the node and check that no events are triggered.
    $edit = [
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Create a new node with a publish-on date, and check that only event 1 is
    // triggered.
    $edit = [
      'title[0][value]' => 'Create node with publish-on date',
      'publish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'publish_on[0][value][time]' => date('H:i:s', time() + 3),
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $assert->pageTextContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Edit this node and check that only event 2 is triggered.
    $edit = [
      'title[0][value]' => 'Edit node with publish-on date',
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Delay to ensure that the date entered is now in the past so that the node
    // will be processed during cron, and assert that event 3 is triggered.
    sleep(5);
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Create a new node with an unpublish-on date, and check that only event 4
    // is triggered.
    $edit = [
      'title[0][value]' => 'Create node with unpublish-on date',
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 3),
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Edit this node and check that only event 5 is triggered.
    $edit = [
      'title[0][value]' => 'Edit node with unpublish-on date',
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Delay to ensure that the date entered is now in the past so that the node
    // will be processed during cron, and assert that event 6 is triggered.
    sleep(5);
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextContains($message[6]);

    // Create a new node with both publish-on and unpublish-on dates, and check
    // that events 1 and event 4 are both triggered.
    $edit = [
      'title[0][value]' => 'Create node with both dates',
      'publish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'publish_on[0][value][time]' => date('H:i:s', time() + 3),
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 4),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 4),
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/add/' . $this->type, $edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $assert->pageTextContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextContains($message[4]);
    $assert->pageTextNotContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Edit this node and check that events 2 and 5 are triggered.
    $edit = [
      'title[0][value]' => 'Edit node with both dates',
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextContains($message[2]);
    $assert->pageTextNotContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextContains($message[5]);
    $assert->pageTextNotContains($message[6]);

    // Delay to ensure that the dates are now in the past so that the node will
    // be processed during cron, and assert that events 3, 5 & 6 are triggered.
    sleep(6);
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');
    $assert->pageTextNotContains($message[1]);
    $assert->pageTextNotContains($message[2]);
    $assert->pageTextContains($message[3]);
    $assert->pageTextNotContains($message[4]);
    $assert->pageTextContains($message[5]);
    $assert->pageTextContains($message[6]);

  }

}
