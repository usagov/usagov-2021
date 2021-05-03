<?php

namespace Drupal\Tests\workbench_email\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workbench_email\Traits\WorkbenchEmailTestTrait;
use Drupal\user\Entity\Role;
use Drupal\workbench_email\Entity\Template;
use Drupal\workbench_moderation\Entity\ModerationStateTransition;

/**
 * Defines a class for testing handlers.
 *
 * @group workbench_email
 * @requires module workbench_moderation
 */
class RecipientTypePluginsTest extends KernelTestBase {

  use WorkbenchEmailTestTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'text',
    'filter',
    'system',
    'user',
    'workbench_email',
    'workbench_moderation',
    'field',
    'workbench_email_test',
  ];

  /**
   * Author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $author;

  /**
   * Last author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $lastAuthor;

  /**
   * Approver.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig([
      'node',
      'workbench_moderation',
      'workbench_email',
      'system',
      'filter',
    ]);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['key_value', 'sequences']);
    $this->installSchema('node', ['node_access']);
    $node_type = $this->createContentType(['type' => 'test']);

    $this->setUpModerationForNodeType($node_type);
    $this->setUpEmailFieldForNodeBundle();
    $this->createEntityReferenceField('node', 'test', 'field_approver', 'Approver', 'user');
    Role::create(['id' => 'editor'])->save();
    $editor = $this->createUser(['use draft_needs_review transition']);
    $editor->setEmail('editor@example.com');
    $editor->addRole('editor');
    $editor->save();
    $editor2 = $this->createUser(['use draft_needs_review transition']);
    $editor2->setEmail('editor2@example.com');
    $editor2->addRole('editor');
    $editor2->save();
    \Drupal::service('account_switcher')->switchTo($editor);

    $this->author = $this->createUser();
    $this->author->setEmail('author@example.com');
    $this->author->save();

    $this->lastAuthor = $this->createUser();
    $this->lastAuthor->setEmail('last-author@example.com');
    $this->lastAuthor->save();

    $this->approver = $this->createUser();
    $this->approver->setEmail('approver@example.com');
    $this->approver->save();
  }

  /**
   * Data provider for testDelivery.
   *
   * @return array
   *   Test cases.
   */
  public function providerDelivery() {
    return [
      'author' => [
        'author',
        ['author@example.com'],
      ],
      'last revision author' => [
        'last_revision_author',
        ['last-author@example.com'],
      ],
      'role' => [
        'role',
        ['editor@example.com', 'editor2@example.com'],
        [
          'roles' => ['editor'],
        ],
        ['user.role.editor'],
      ],
      'roles_with_access' => [
        'roles_with_access',
        ['editor@example.com'],
        [
          'roles' => ['editor'],
        ],
        ['user.role.editor'],
      ],
      'email field' => [
        'email',
        ['random@example.com'],
        [
          'fields' => ['node:field_email'],
        ],
        ['field.storage.node.field_email'],
      ],
      'entity reference field' => [
        'entity_reference_user',
        ['approver@example.com'],
        [
          'fields' => ['node:field_approver'],
        ],
        ['field.storage.node.field_approver'],
      ],
      'fixed email' => [
        'fixed_email',
        ['fixed@example.com'],
        [
          'recipients' => 'fixed@example.com',
        ],
      ],
    ];
  }

  /**
   * Tests recipient delivery.
   *
   * @param string $pluginId
   *   Recipient plugin ID.
   * @param array $expectedRecipients
   *   Expected recipients.
   * @param array $settings
   *   Plugin settings.
   * @param array $expectedDependencies
   *   Expected configuration dependencies.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider providerDelivery
   */
  public function testDelivery($pluginId, array $expectedRecipients, array $settings = [], array $expectedDependencies = []) {
    $template = Template::create([
      'id' => 'test',
      'label' => 'Test',
      'body' => [
        'value' => 'Content with title [node:title] needs review.',
        'format' => 'plain_text',
      ],
      'subject' => 'Content needs review: [node:title]',
      'recipient_types' => [
        $pluginId => [
          'id' => $pluginId,
          'provider' => 'workbench_email',
          'status' => 1,
          'settings' => $settings,
        ],
      ],
    ]);
    $template->save();
    if ($expectedDependencies) {
      $dependencies = $template->calculateDependencies()->getDependencies()['config'];
      $this->assertEquals($expectedDependencies, $dependencies);
    }
    $transition = ModerationStateTransition::load('draft_needs_review');
    $transition->setThirdPartySetting('workbench_email', 'workbench_email_templates', ['test' => 'test']);
    $transition->save();
    $this->assertContains('workbench_email.workbench_email_template.test', $transition->calculateDependencies()->getDependencies()['config']);

    $node = Node::create([
      'title' => 'test',
      'uid' => $this->author->id(),
      'type' => 'test',
      'field_email' => 'random@example.com',
      'field_approver' => $this->approver->id(),
      'revision_uid' => $this->lastAuthor->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node->setNewRevision();
    $node->revision_uid = $this->lastAuthor->id();
    $node->save();

    // Reset email.
    $this->container->get('state')->set('system.test_mail_collector', []);

    // Send for review.
    $node->moderation_state = 'needs_review';
    $node->revision_uid = $this->lastAuthor->id();
    $node->setNewRevision();
    $node->save();

    // Check mail goes to recipients.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $this->assertEqualsCanonicalizing($expectedRecipients, array_map(function (array $mail) {
      return $mail['to'];
    }, $captured_emails));
    foreach ($captured_emails as $email) {
      $this->assertEquals(sprintf('Content needs review: %s (node id: %s)', $node->getTitle(), $node->id()), $email['subject']);
      $this->assertStringContainsString(sprintf('Content with title %s needs review.', $node->label()), preg_replace('/\s+/', ' ', $email['body']));
    }
  }

}
