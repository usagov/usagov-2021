<?php

namespace Drupal\Tests\content_lock_timeout\Functional;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Test content_lock_timeout sub module.
 *
 * @group content_lock
 */
class ContentLockTimeoutTest extends BrowserTestBase {

  use TaxonomyTestTrait;
  use CronRunTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'language',
    'user',
    'node',
    'field',
    'field_ui',
    'taxonomy',
    'block',
    'block_content',
    'content_lock',
    'content_lock_timeout',
    'content_lock_timeout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Array standard permissions for normal user.
   *
   * @var array
   */
  protected $permissions1;

  /**
   * Array standard permissions for user2.
   *
   * @var array
   */
  protected $permissions2;

  /**
   * User with permission to administer entities.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Standard User.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user1;

  /**
   * Standard User.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user2;

  /**
   * A node created.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $article1;

  /**
   * A vocabulary created.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * A term created.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term1;

  /**
   * A Block created.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $block1;

  /**
   * Lock service.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLock
   */
  protected $lockService;

  /**
   * Setup and Rebuild node access.
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);

    $this->adminUser = $this->drupalCreateUser([
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer users',
      'administer blocks',
      'administer taxonomy',
      'administer content lock',
    ]);

    $this->permissions1 = [
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'administer blocks',
      'administer taxonomy',
    ];

    $this->permissions2 = [
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'administer blocks',
      'administer taxonomy',
      'break content lock',
    ];

    // Create articles nodes.
    $this->article1 = $this->drupalCreateNode(['type' => 'article', 'title' => 'Article 1']);

    // Create vocabulary and terms.
    $this->vocabulary = $this->createVocabulary();
    $this->term1 = $this->createTerm($this->vocabulary);

    $this->user1 = $this->drupalCreateUser($this->permissions1);
    $this->user2 = $this->drupalCreateUser($this->permissions2);

    node_access_rebuild();
    $this->cronRun();


    $this->setNewDatetimeTimeService();

    $this->drupalLogin($this->adminUser);
    $edit = [
      'content_lock_timeout_minutes' => 10,
      'content_lock_timeout_on_edit' => 1,
    ];
    $this->drupalPostForm('/admin/config/content/content_lock/timeout', $edit, t('Save configuration'));

    $this->lockService = \Drupal::service('content_lock');
  }

  /**
   * Change the service.yml to set own datetime.time service.
   *
   * @see FunctionalTestSetupTrait::setContainerParameter
   */
  protected function setNewDatetimeTimeService() {
    $filename = $this->siteDirectory . '/services.yml';
    chmod($filename, 0666);

    // @todo Remove preg_replace() once
    //   https://github.com/symfony/symfony/pull/25787 is in Symfony 3.4.
    $content = file_get_contents($filename);
    $content = preg_replace('/:$\n^\s+{\s*}$/m', ': {}', $content);
    $services = Yaml::decode($content);
    $services['services']['datetime.time'] = [
      'class' => 'Drupal\content_lock_timeout_test\TimeChanger',
      'arguments' => ['@request_stack'],
    ];
    file_put_contents($filename, Yaml::encode($services));

    // Ensure that the cache is deleted for the yaml file loader.
    $file_cache = FileCacheFactory::get('container_yaml_loader');
    $file_cache->delete($filename);
    $this->rebuildContainer();
  }

  /**
   * Test content lock timeout with nodes.
   */
  public function testContentLockNode() {
    // We protect the bundle created.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));

    $this->doTestForEntity($this->article1);
  }

  /**
   * Test content lock timeout with terms.
   */
  public function testContentLockTerm() {
    // We protect the bundle created.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'taxonomy_term[bundles][' . $this->term1->bundle() . ']' => 1,
    ];
    $this->drupalPostForm('admin/config/content/content_lock', $edit, t('Save configuration'));

    $this->doTestForEntity($this->term1);
  }

  /**
   * Run the same tests for node, block and term.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to tests.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function doTestForEntity(EntityInterface $entity) {
    // We lock article1.
    $this->drupalLogin($this->user2);

    $this->lockContentByUser1($entity);

    // Content should be locked.
    $this->drupalGet($entity->toUrl('edit-form')->toString());
    $this->assertText(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user1->getDisplayName(),
    ]));

    // Jump into future to release lock.
    \Drupal::time()->setCurrentTime(time() + 60 * 60);
    $this->cronRun();
    \Drupal::time()->resetCurrentTime();

    // Content should be unlocked by cron.
    $this->assertNoLockOnContent($entity);
    $this->drupalGet($entity->toUrl('edit-form')->toString());
    $this->assertText(t('This content is now locked against simultaneous editing.'));

    $this->drupalLogout();

    // There should be no lock on the content after logout.
    $this->assertNoLockOnContent($entity);

    $this->lockContentByUser1($entity);

    $this->drupalLogin($this->user2);

    // Content should be locked.
    $this->drupalGet($entity->toUrl('edit-form')->toString());
    $this->assertText(t('This content is being edited by the user @name and is therefore locked to prevent other users changes.', [
      '@name' => $this->user1->getDisplayName(),
    ]));

    // Jump into the future.
    \Drupal::time()->setCurrentTime(time() + 60 * 60);
    // Lock should be release by form prepare.
    $this->drupalGet($entity->toUrl('edit-form')->toString());
    $this->assertText(t('This content is now locked against simultaneous editing.'));
  }

  /**
   * Create lock from user 1.
   *
   * As logout is removing locks, it is only possible to set a lock from another
   * user with the lock service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should be locked.
   */
  protected function lockContentByUser1(EntityInterface $entity) {
    $this->lockService->releaseAllUserLocks($this->user2->id());
    $this->lockService->locking($entity->id(), $entity->language()->getId(), 'edit', $this->user1->id(), $entity->getEntityTypeId());
    $lock = $this->lockService->fetchLock($entity->id(), $entity->language()->getId(), 'edit', $entity->getEntityTypeId());
    $this->assertNotNull($lock, 'Lock present');
    $this->assertEqual($this->user1->label(), $lock->name, 'Lock present for correct user.');
  }

  /**
   * Assert if no lock is present for content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should not have a lock.
   */
  protected function assertNoLockOnContent(EntityInterface $entity) {
    $lock = $this->lockService->fetchLock($entity->id(), $entity->language()->getId(), 'edit', $entity->getEntityTypeId());
    $this->assertFalse($lock, 'No lock present.');
  }

}
