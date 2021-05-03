<?php

namespace Drupal\Tests\scheduler\Traits;

use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Generic setup for all Scheduler tests.
 *
 * This is used in SchedulerBrowserTestBase and SchedulerJavascriptTestBase.
 */
trait SchedulerSetupTrait {

  use CronRunTrait;

  // @todo Remove this when core 8.8 is the lowest supported version.
  // @see https://www.drupal.org/project/scheduler/issues/3136744
  use PhpunitCompatibilityCore87Trait;

  /**
   * A user with administration rights.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * A user with permission to schedule content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $schedulerUser;

  /**
   * The internal name of the standard content type created for testing.
   *
   * @var string
   */
  protected $type;

  /**
   * The readable name of the standard content type created for testing.
   *
   * @var string
   */
  protected $typeName;

  /**
   * The node type object.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nodetype;

  /**
   * The node type object which is not enabled for scheduling.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nonSchedulerNodetype;

  /**
   * The node storage object.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The request time stored as interger for direct re-use in many tests.
   *
   * @var int
   */
  protected $requestTime;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Set common properties, define content types and create users.
   */
  public function schedulerSetUp() {

    // Create a test content type with id 'testpage' and name 'Test Page'.
    // The tests should use $this->type and $this->typeName and not use
    // $this->nodetype->get('type') or $this->nodetype->get('name'), nor have
    // the hard-coded strings 'testpage' or 'Test Page'.
    $this->type = 'testpage';
    $this->typeName = 'Test Page';
    /** @var NodeTypeInterface $nodetype */
    $this->nodetype = $this->drupalCreateContentType([
      'type' => $this->type,
      'name' => $this->typeName,
    ]);

    // Add scheduler functionality to the content type.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // The majority of tests use the standard Scheduler-enabled content type but
    // we also need a content type which is not enabled for Scheduler.
    $this->nonSchedulerNodeType = $this->drupalCreateContentType([
      'type' => 'not-for-scheduler',
      'name' => 'Non Scheduler Content',
    ]);

    // Define nodeStorage for use in many tests.
    /** @var EntityStorageInterface $nodeStorage */
    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');

    // Create an administrator user having the main admin permissions, full
    // rights on the test content type and all of the Scheduler permissions.
    // 'access site reports' is required for admin/reports/dblog.
    // 'administer site configuration' is required for admin/reports/status.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'access content overview',
      'access site reports',
      'administer nodes',
      'administer site configuration',
      'create ' . $this->type . ' content',
      'edit any ' . $this->type . ' content',
      'delete any ' . $this->type . ' content',
      'create ' . $this->nonSchedulerNodeType->id() . ' content',
      'edit any ' . $this->nonSchedulerNodeType->id() . ' content',
      'delete any ' . $this->nonSchedulerNodeType->id() . ' content',
      'view own unpublished content',
      'administer scheduler',
      'schedule publishing of nodes',
      'view scheduled content',
    ]);

    // Create an ordinary Scheduler user, with permission to create and schedule
    // content but not with administrator permissions.
    $this->schedulerUser = $this->drupalCreateUser([
      'create ' . $this->type . ' content',
      'edit own ' . $this->type . ' content',
      'delete own ' . $this->type . ' content',
      'view own unpublished content',
      'schedule publishing of nodes',
      'view scheduled content',
    ]);

    // Store the database connection for re-use in the actual tests.
    $this->database = $this->container->get('database');

    // Determine the request time and save for re-use in the actual tests.
    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();

    // Store the core dateFormatter service for re-use in the actual tests.
    $this->dateFormatter = $this->container->get('date.formatter');

  }

}
