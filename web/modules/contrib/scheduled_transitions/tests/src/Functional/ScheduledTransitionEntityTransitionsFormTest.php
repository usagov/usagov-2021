<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider as RouteProvider;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestEntity as TestEntity;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\user\Entity\User;

/**
 * Tests the route which shows all transitions for an entity.
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\Form\ScheduledTransitionForm
 */
class ScheduledTransitionEntityTransitionsFormTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use ScheduledTransitionTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test_revlog',
    'scheduled_transitions_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'content_translation',
    'language',
    'locale',
    'system',
  ];

  /**
   * Tests transitions appear on list according to page context language.
   *
   * Transitions irrelevant to the language context must not show.
   *
   * Language by route context is automatically enabled when installing
   * language.module with the 'URL' (Language from the URL (Path prefix or
   * domain) negotiator method plugin.
   */
  public function testTransitionsByPageLanguage() {
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

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      Permissions::viewScheduledTransitionsPermission('st_entity_test', 'st_entity_test'),
    ]);
    $this->drupalLogin($currentUser);

    $entity = TestEntity::create(['type' => 'st_entity_test']);
    $entity->name = 'defaultName';
    $de = $entity->addTranslation('de');
    $fr = $entity->addTranslation('fr');
    $de->name = 'deName';
    $fr->name = 'frName';
    $entity->save();

    $author = User::create(['name' => $this->randomMachineName()]);
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

    $defaultUrl = $entity->toUrl(RouteProvider::LINK_TEMPLATE);
    $this->drupalGet($defaultUrl);
    $this->assertSession()->addressEquals('/st_entity_test/1/scheduled-transitions');
    $this->assertSession()->pageTextContains('There are no scheduled transitions for defaultName');

    $deUrl = $entity->getTranslation('de')->toUrl(RouteProvider::LINK_TEMPLATE);
    $this->drupalGet($deUrl);
    $this->assertSession()->addressEquals('/de/st_entity_test/1/scheduled-transitions');
    $this->assertSession()->pageTextNotContains('There are no scheduled transitions for deName');
    $this->assertSession()->elementTextContains('css', 'table tr:nth-child(1) > td:nth-child(2)', 'Draft');
    $this->assertSession()->elementTextContains('css', 'table tr:nth-child(1) > td:nth-child(3)', 'Published');
    $this->assertSession()->elementTextContains('css', 'table tr:nth-child(1) > td:nth-child(5)', $author->label());

    $frUrl = $entity->getTranslation('fr')->toUrl(RouteProvider::LINK_TEMPLATE);
    $this->drupalGet($frUrl);
    $this->assertSession()->addressEquals('/fr/st_entity_test/1/scheduled-transitions');
    $this->assertSession()->pageTextContains('There are no scheduled transitions for frName');
  }

}
