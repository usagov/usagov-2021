<?php

namespace Drupal\Tests\message_notify\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message_notify\Exception\MessageNotifyException;
use Drupal\user\Entity\User;

/**
 * Test the Message notifier plugins handling.
 *
 * @group message_notify
 */
class MessageNotifyTest extends KernelTestBase {

  /**
   * Testing message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'message_notify_test',
    'message_notify',
    'message',
    'user',
    'system',
    'field',
    'text',
    'filter',
    'filter_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig([
      'message',
      'message_notify',
      'message_notify_test',
      'filter_test',
    ]);
    $this->installSchema('system', ['sequences']);

    $this->messageTemplate = MessageTemplate::load('message_notify_test');

    $this->messageNotifier = $this->container->get('message_notify.sender');
  }

  /**
   * Test send method.
   *
   * Check the correct info is sent to delivery.
   */
  public function testDeliver() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $message->message_text_another = 'another field';
    $this->messageNotifier->send($message, [], 'test');

    // The test notifier added the output to the message.
    $output = $message->output;
    $text = $message->getText();
    $this->assertContains((string) $text[1], (string) $output['foo']);
    $this->assertContains('another field', (string) $output['foo']);
    $this->assertContains((string) $text[0], (string) $output['bar']);
    $this->assertNotContains('another field', (string) $output['bar']);
  }

  /**
   * Test Message save on delivery.
   */
  public function testPostSendMessageSave() {
    $account = User::create(['name' => $this->randomMachineName()]);
    $account->save();
    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = FALSE;
    $this->messageNotifier->send($message, [], 'test');
    $this->assertTrue($message->id(), 'Message saved after successful delivery.');

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = TRUE;
    $this->messageNotifier->send($message, [], 'test');
    $this->assertFalse($message->id(), 'Message not saved after unsuccessful delivery.');

    // Disable saving Message on delivery.
    $options = [
      'save on fail' => FALSE,
      'save on success' => FALSE,
    ];

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    // @todo See above.
    $message->fail = FALSE;
    $this->messageNotifier->send($message, $options, 'test');
    $this->assertTrue($message->isNew(), 'Message not saved after successful delivery.');

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = TRUE;
    $this->messageNotifier->send($message, $options, 'test');
    $this->assertTrue($message->isNew(), 'Message not saved after unsuccessful delivery.');
  }

  /**
   * Test populating the rednered output to fields.
   */
  public function testPostSendRenderedField() {
    $this->attachRenderedFields();

    // Test plain fields.
    $options = [
      'rendered fields' => [
        'foo' => 'rendered_foo',
        'bar' => 'rendered_bar',
      ],
    ];

    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $this->messageNotifier->send($message, $options, 'test');
    $this->assertTrue($message->rendered_foo->value && $message->rendered_bar->value, 'Message is rendered to fields.');

    // Test field with text-processing.
    $options = [
      'rendered fields' => [
        'foo' => 'rendered_baz',
        'bar' => 'rendered_bar',
      ],
    ];

    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $this->messageNotifier->send($message, $options, 'test');
    $this->assertTrue($message->rendered_baz->value && $message->rendered_bar->value, 'Message is rendered to fields with text-processing.');

    // Test missing view mode key in the rendered fields.
    $options = [
      'rendered fields' => [
        'foo' => 'rendered_foo',
        // No "bar" field.
      ],
    ];
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    try {
      $this->messageNotifier->send($message, $options, 'test');
      $this->fail('Can save rendered message with missing view mode.');
    }
    catch (MessageNotifyException $e) {
      $this->pass('Cannot save rendered message with missing view mode.');
    }

    // Test invalid field name.
    $options = [
      'rendered fields' => [
        'foo' => 'wrong_field',
        'bar' => 'rendered_bar',
      ],
    ];
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    try {
      $this->messageNotifier->send($message, $options, 'test');
      $this->fail('Can save rendered message to non-existing field.');
    }
    catch (MessageNotifyException $e) {
      $this->pass('Cannot save rendered message to non-existing field.');
    }
  }

  /**
   * Helper function to attach rendred fields.
   *
   * @see MessageNotifyTest::testPostSendRenderedField()
   */
  protected function attachRenderedFields() {
    foreach (['rendered_foo', 'rendered_bar', 'rendered_baz'] as $field_name) {
      // Use formatted text for `baz`, plain for others.
      $config = [
        'field_name' => $field_name,
        'type' => 'string_long',
        'entity_type' => 'message',
      ];
      if ($field_name == 'rendered_baz') {
        $config['type'] = 'text_long';
      }
      $field_storage = FieldStorageConfig::create($config);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_name' => $field_name,
        'bundle' => $this->messageTemplate->id(),
        'entity_type' => 'message',
        'label' => $field_name,
      ]);

      $field->save();
    }
  }

}
