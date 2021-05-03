<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\rules\Context\ContextConfig;

/**
 * Tests the four conditions that Scheduler provides for use in Rules module.
 *
 * @group scheduler
 * @group legacy
 * @todo Remove the 'legacy' tag when Rules no longer uses deprecated code.
 * @see https://www.drupal.org/project/scheduler/issues/2924353
 */
class SchedulerRulesConditionsTest extends SchedulerBrowserTestBase {

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

    // Create a published node.
    $this->node = $this->drupalCreateNode([
      'title' => 'Rules Test Node',
      'type' => $this->type,
      'uid' => $this->schedulerUser->id(),
      'status' => TRUE,
    ]);
  }

  /**
   * Tests the conditions for whether a nodetype is enabled for Scheduler.
   */
  public function testNodeTypeEnabledConditions() {
    // Create a reaction rule to display a message when viewing a node of a type
    // that is enabled for scheduled publishing.
    // "viewing content" actually means "viewing PUBLISHED content".
    $rule1 = $this->expressionManager->createRule();
    $rule1->addCondition('scheduler_condition_publishing_is_enabled',
      ContextConfig::create()->map('node', 'node')
    );
    $message1 = 'RULES message 1. This node type is enabled for scheduled publishing.';
    $rule1->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message1)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule1',
      'events' => [['event_name' => 'rules_entity_view:node']],
      'expression' => $rule1->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing a node of a type
    // that is enabled for scheduled unpublishing.
    $rule2 = $this->expressionManager->createRule();
    $rule2->addCondition('scheduler_condition_unpublishing_is_enabled',
      ContextConfig::create()->map('node', 'node')
    );
    $message2 = 'RULES message 2. This node type is enabled for scheduled unpublishing.';
    $rule2->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message2)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule2',
      'events' => [['event_name' => 'rules_entity_view:node']],
      'expression' => $rule2->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing a node of a type
    // that is NOT enabled for scheduled publishing.
    $rule3 = $this->expressionManager->createRule();
    $rule3->addCondition('scheduler_condition_publishing_is_enabled',
      ContextConfig::create()->map('node', 'node')->negateResult()
    );
    $message3 = 'RULES message 3. This node type is not enabled for scheduled publishing.';
    $rule3->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message3)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule3',
      'events' => [['event_name' => 'rules_entity_view:node']],
      'expression' => $rule3->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing a node of a type
    // that is NOT enabled for scheduled unpublishing.
    $rule4 = $this->expressionManager->createRule();
    $rule4->addCondition('scheduler_condition_unpublishing_is_enabled',
      ContextConfig::create()->map('node', 'node')->negateResult()
    );
    $message4 = 'RULES message 4. This node type is not enabled for scheduled unpublishing.';
    $rule4->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message4)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule4',
      'events' => [['event_name' => 'rules_entity_view:node']],
      'expression' => $rule4->getConfiguration(),
    ]);
    $config_entity->save();

    $assert = $this->assertSession();

    // View the node and check the default position - that the node type is
    // enabled for both publishing and unpublishing.
    $this->drupalGet('node/' . $this->node->id());
    $assert->pageTextContains($message1);
    $assert->pageTextContains($message2);
    $assert->pageTextNotContains($message3);
    $assert->pageTextNotContains($message4);

    // Turn off scheduled publishing for the node type and check the rules.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_enable', FALSE)->save();
    // Flushing the caches was not required when using WebTestBase but is needed
    // after converting to BrowserTestBase.
    drupal_flush_all_caches();
    $this->drupalGet('node/' . $this->node->id());
    $assert->pageTextNotContains($message1);
    $assert->pageTextContains($message2);
    $assert->pageTextContains($message3);
    $assert->pageTextNotContains($message4);

    // Turn off scheduled unpublishing for the node type and the check again.
    $this->nodetype->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    drupal_flush_all_caches();
    $this->drupalGet('node/' . $this->node->id());
    $assert->pageTextNotContains($message1);
    $assert->pageTextNotContains($message2);
    $assert->pageTextContains($message3);
    $assert->pageTextContains($message4);

  }

  /**
   * Tests the conditions for whether a node is scheduled.
   */
  public function testNodeIsScheduledConditions() {
    // Create a reaction rule to display a message when a node is updated and
    // is not scheduled for publishing.
    $rule5 = $this->expressionManager->createRule();
    $rule5->addCondition('scheduler_condition_node_scheduled_for_publishing',
      ContextConfig::create()->map('node', 'node')->negateResult()
    );
    $message5 = 'RULES message 5. This content is not scheduled for publishing.';
    $rule5->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message5)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule5',
      'events' => [['event_name' => 'rules_entity_update:node']],
      'expression' => $rule5->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when a node is updated and
    // is not scheduled for unpublishing.
    $rule6 = $this->expressionManager->createRule();
    $rule6->addCondition('scheduler_condition_node_scheduled_for_unpublishing',
      ContextConfig::create()->map('node', 'node')->negateResult()
    );
    $message6 = 'RULES message 6. This content is not scheduled for unpublishing.';
    $rule6->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message6)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule6',
      'events' => [['event_name' => 'rules_entity_update:node']],
      'expression' => $rule6->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when a node is updated and
    // is scheduled for publishing.
    $rule7 = $this->expressionManager->createRule();
    $rule7->addCondition('scheduler_condition_node_scheduled_for_publishing',
      ContextConfig::create()->map('node', 'node')
    );
    $message7 = 'RULES message 7. This content is scheduled for publishing.';
    $rule7->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message7)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule7',
      'events' => [['event_name' => 'rules_entity_update:node']],
      'expression' => $rule7->getConfiguration(),
    ]);
    $config_entity->save();

    $assert = $this->assertSession();

    // Create a reaction rule to display a message when a node is updated and
    // is scheduled for unpublishing.
    $rule8 = $this->expressionManager->createRule();
    $rule8->addCondition('scheduler_condition_node_scheduled_for_unpublishing',
      ContextConfig::create()->map('node', 'node')
    );
    $message8 = 'RULES message 8. This content is scheduled for unpublishing.';
    $rule8->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message8)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule8',
      'events' => [['event_name' => 'rules_entity_update:node']],
      'expression' => $rule8->getConfiguration(),
    ]);
    $config_entity->save();

    $this->drupalLogin($this->schedulerUser);

    // Edit the node but do not enter any scheduling dates.
    $edit = [
      'body[0][value]' => $this->randomString(30),
    ];
    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $edit, 'Save');

    $assert->pageTextContains($message5);
    $assert->pageTextContains($message6);
    $assert->pageTextNotContains($message7);
    $assert->pageTextNotContains($message8);

    // Edit the node and set a publish_on date.
    $edit = [
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('+1 day', $this->requestTime)),
    ];
    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $edit, 'Save');

    $assert->pageTextNotContains($message5);
    $assert->pageTextContains($message6);
    $assert->pageTextContains($message7);
    $assert->pageTextNotContains($message8);

    // Edit the node and set an unpublish_on date.
    $edit = [
      'unpublish_on[0][value][date]' => date('Y-m-d', strtotime('+2 day', $this->requestTime)),
      'unpublish_on[0][value][time]' => date('H:i:s', strtotime('+2 day', $this->requestTime)),
    ];
    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $edit, 'Save');

    $assert->pageTextNotContains($message5);
    $assert->pageTextNotContains($message6);
    $assert->pageTextContains($message7);
    $assert->pageTextContains($message8);

  }

}
