<?php

namespace Drupal\Tests\scheduled_publish\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\scheduled_publish\Service\ScheduledPublishCron;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Class FormatterTest
 *
 * @package Drupal\Tests\scheduled_publish\Kernel
 * @group scheduled_publish
 */
class FormatterTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'taxonomy',
    'scheduled_publish',
    'content_moderation',
    'workflows',
    'datetime',
    'user',
  ];

  /** @var ScheduledPublishCron */
  private $scheduledUpdateService;


  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setInstallProfile('standard');
    $this->installConfig([
      'field',
      'system',
      'content_moderation',
      'scheduled_publish',
    ]);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig('content_moderation');

    $this->scheduledUpdateService = \Drupal::service('scheduled_publish.update');
    $this->createNodeType();
  }

  /**
   * Creates a page node type to test with, ensuring that it's moderated.
   */
  protected function createNodeType() {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_scheduled_publish',
      'type' => 'scheduled_publish',
      'entity_type' => 'node',
    ]);

    $field_storage->save();

    $node_type = NodeType::create([
      'type' => 'page',
    ]);
    $node_type->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_scheduled_publish',
      'bundle' => 'page',
      'label' => 'Test field',
    ])->save();

    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();
  }

  public function testRenderOutput() {

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'default',
      'status' => TRUE,
    ]);

    $display_options = [
      'label' => 'above',
      'type' => 'scheduled_publish_generic_formatter',
      'settings' => [
        'date_format' => 'html_date',
        'text_pattern' => '%moderation_state% ------ %date%',
      ],
    ];
    $display->setComponent('field_scheduled_publish', $display_options);

    $display->save();
    $page = Node::create([
      'type' => 'page',
      'title' => 'A',
      'uid' => 1,
    ]);

    $page->moderation_state->value = 'draft';
    $page->set('field_scheduled_publish', [
      'moderation_state' => 'published',
      'value' => '2021-01-31T00:00:00',
    ]);
    $page->save();

    $nodeID = $page->id();

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $storage->load($nodeID);
    $build = $view_builder->view($node, 'default');

    $output = \Drupal::service('renderer')->renderPlain($build);

    self::assertTrue((bool)$nodeID);
    self::assertNotFalse(strpos($output, '<div>published ------ 2021-01-31</div>'));

    $this->scheduledUpdateService->doUpdate();

    $loadedNode = Node::load($nodeID);
  }
}
