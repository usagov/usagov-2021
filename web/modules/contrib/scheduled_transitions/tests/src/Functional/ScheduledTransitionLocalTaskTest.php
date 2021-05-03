<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestEntity as TestEntity;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests the text displayed in local task [tab].
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionLocalTaskTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use ScheduledTransitionTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'scheduled_transitions_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'block',
    'language',
    'field',
    'system',
    'locale',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Tests local task tab title depending on viewed translation.
   */
  public function testTabTitleByTranslation() {
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $languageNegotiator = \Drupal::service('language_negotiator');
    $languageNegotiator->saveConfiguration('language_content', [
      LanguageNegotiationUrl::METHOD_ID => 1,
    ]);
    // Rebuild so container picks up new languages and enabled negotiator
    // plugins.
    $this->rebuildContainer();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();
    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $entity = TestEntity::create(['type' => 'st_entity_test']);
    $de = $entity->addTranslation('de');
    $fr = $entity->addTranslation('fr');
    $de->name = 'deName';
    $fr->name = 'frName';
    $entity->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 1,
      // Transition 'de'.
      'entity_revision_langcode' => 'de',
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => 'published',
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'view scheduled transitions st_entity_test st_entity_test',
    ]);
    $this->drupalLogin($currentUser);

    // No transitions for default language.
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'nav.tabs', 'Scheduled transitions (0)');

    // No transitions for 'de' language.
    $this->drupalGet($entity->getTranslation('de')->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'nav.tabs', 'Scheduled transitions (1)');

    // No transitions for 'fr' language.
    $this->drupalGet($entity->getTranslation('fr')->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'nav.tabs', 'Scheduled transitions (0)');
  }

  /**
   * Tests latest revision tab.
   *
   * @todo add another test method for revision log when there is a generic
   * log controller. https://www.drupal.org/project/drupal/issues/2350939
   */
  public function testLatestRevisionTab() {
    /** @var \Drupal\Core\Entity\TranslatableRevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('st_entity_test');

    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      'view latest version',
      'view any unpublished content',
    ]);
    $this->drupalLogin($currentUser);

    $entity = TestEntity::create(['type' => 'st_entity_test']);

    // Test normal Drupal + CM behaviour.
    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->name = 'rev1';
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('View');
    $this->assertSession()->linkExists('Edit');
    // Draft + default results = no latest link yet.
    $this->assertSession()->linkNotExists('Latest version');

    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->name = 'rev2';
    $entity->moderation_state = 'published';
    $entity->save();
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('View');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkNotExists('Latest version');

    // Do not change any storage fields this time.
    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('View');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Latest version');

    // Run the transition to a new default revision, the 'Latest version' tab
    // must no longer be present.
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => $entity->getRevisionId(),
      'author' => 1,
      'workflow' => $workflow->id(),
      'moderation_state' => 'published',
      'transition_on' => (new \DateTime('1 year ago'))->getTimestamp(),
    ]);
    $scheduledTransition->save();
    $this->runTransition($scheduledTransition);

    $this->drupalGet($entity->toUrl());
    $this->assertSession()->linkExists('View');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkNotExists('Latest version');
  }

}
