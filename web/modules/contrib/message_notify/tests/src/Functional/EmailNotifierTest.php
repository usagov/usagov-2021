<?php

namespace Drupal\Tests\message_notify\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the email notifier plugin.
 *
 * @group message_notify
 *
 * Uses web test base since that provides the mock email handler.
 */
class EmailNotifierTest extends BrowserTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['text', 'message_notify_test', 'filter_test'];

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
  public function setUp() {
    parent::setUp();

    $this->messageTemplate = MessageTemplate::load('message_notify_test');
    $this->messageNotifier = $this->container->get('message_notify.sender');
  }

  /**
   * Test that mails are properly sent.
   */
  public function testEmailNotifier() {
    $account = $this->drupalCreateUser();
    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $this->messageNotifier->send($message, [], 'email');
    $this->assertMail('subject', 'first partial', 'Expected email subject sent');
    $this->assertMail('body', "second partial\n\n", 'Expected email body sent');
  }

}
