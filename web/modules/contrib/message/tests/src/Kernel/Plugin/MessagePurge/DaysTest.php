<?php

namespace Drupal\Tests\message\Kernel\Plugin\MessagePurge;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;

/**
 * Integration tests for the 'days' purge plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\MessagePurge\Days
 *
 * @group message
 */
class DaysTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['message', 'user'];

  /**
   * The plugin to test.
   *
   * @var \Drupal\message\Plugin\MessagePurge\Days
   */
  protected $plugin;

  /**
   * A message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $template;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $timeService;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('message');

    $this->template = MessageTemplate::create([
      'template' => 'foo',
    ]);
    $this->template->save();
    $this->timeService = $this->container->get('datetime.time');
  }

  /**
   * Tests the fetch method.
   *
   * @covers ::fetch
   */
  public function testFetch() {
    $configuration = [
      'weight' => 4,
      'data' => [
        'days' => 2,
      ],
    ];
    $this->createPlugin($configuration);

    // No IDs should return if there are no messages.
    $this->assertEquals([], $this->plugin->fetch($this->template));

    // Add some message using this template.
    /** @var \Drupal\message\MessageInterface[] $messages */
    $messages = [];
    foreach (range(1, 5) as $i) {
      $message = Message::create(['template' => $this->template->id()]);
      $message->save();
      $messages[$i] = $message;
    }

    // None should be returned as they are all newer than 2 days.
    $this->createPlugin($configuration);
    $this->assertEquals([], $this->plugin->fetch($this->template));

    // Set message 3 to be 3 days old.
    $messages[3]->set('created', $this->timeService->getRequestTime() - 86400 * 3);
    $messages[3]->save();
    $this->createPlugin($configuration);
    $this->assertEquals([3 => 3], $this->plugin->fetch($this->template));
  }

  /**
   * Tests the processing of messages to be purged.
   *
   * @covers ::process
   */
  public function testProcess() {
    // Add some message using this template.
    /** @var \Drupal\message\MessageInterface[] $messages */
    $messages = [];
    foreach (range(1, 5) as $i) {
      $message = Message::create(['template' => $this->template->id()]);
      $message->set('created', $this->timeService->getRequestTime() - 86400);
      $message->save();
      $messages[$i] = $message;
    }

    // Verify the messages are there.
    $configuration = [
      'weight' => 4,
      'data' => [
        'days' => 0,
      ],
    ];
    $this->createPlugin($configuration);
    $this->assertEquals(5, count($this->plugin->fetch($this->template)));

    $this->plugin->process(array_keys($messages));
    $this->container->get('cron')->run();

    $this->createPlugin($configuration);
    $this->assertEquals([], $this->plugin->fetch($this->template));
  }

  /**
   * Set the plugin with the given configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  protected function createPlugin(array $configuration) {
    $this->plugin = $this->container->get('plugin.manager.message.purge')->createInstance('days', $configuration);
  }

}
