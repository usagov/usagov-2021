<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the scheduling functions for node translations.
 *
 * @group scheduler
 */
class SchedulerMultilingualTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['content_translation'];

  /**
   * A user with permission to translate content and use scheduler.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $translatorUser;

  /**
   * Array of language information for translations.
   *
   * @var array
   */
  protected $languages;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a user with the required translation permissions.
    // 'administer languages' for url admin/config/regional/content-language.
    // 'administer content translation' to show the list of content fields at
    // url admin/config/regional/content-language.
    // 'create content translations' for the 'translations' tab on node pages
    // url node/*/translations.
    // 'translate any entity' for the 'add translation' link on the translations
    // page, url node/*/translations/add/.
    $this->translatorUser = $this->drupalCreateUser([
      'administer languages',
      'administer content translation',
      'create content translations',
      'translate any entity',
    ]);

    // Get the additional role already assigned to the scheduler admin user
    // created in SchedulerBrowserTestBase and add this role to the translator
    // user, to avoid switching between users throughout this test.
    $admin_roles = $this->adminUser->getRoles();
    // Key 0 is 'authenticated' role. Key 1 is the first real role.
    $this->translatorUser->addRole($admin_roles[1]);
    $this->translatorUser->save();
    $this->drupalLogin($this->translatorUser);

    // Allow scheduler dates in the past to be published on next cron run.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Enable the content type for translation.
    $this->container->get('content_translation.manager')->setEnabled('node', $this->type, TRUE);

    // Make three additional languages available. 'en' is added here as the last
    // code and it should not be defined as a configurable language.
    $langcodes = ['am', 'bg', 'ca', 'en'];
    ConfigurableLanguage::createFromLangcode($langcodes[0])->save();
    ConfigurableLanguage::createFromLangcode($langcodes[1])->save();
    ConfigurableLanguage::createFromLangcode($langcodes[2])->save();

    // Get the language names and store for later use.
    $languages = \Drupal::languageManager()->getLanguages();
    foreach ($langcodes as $key => $code) {
      $this->languages[$key] = [
        'code' => $code,
        'name' => $languages[$code]->getName(),
      ];
    }
  }

  /**
   * Helper function to assert the published status of translations.
   *
   * @param int $nid
   *   The node id of the node to check.
   * @param string $description
   *   Text explaining what part of the test is being checked.
   * @param array $status
   *   Array of expected status values for the translations. The original
   *   content status is first, followed by any number of translations.
   */
  private function checkStatus($nid, $description, array $status) {

    // Reset the cache and reload the node.
    $this->nodeStorage->resetCache([$nid]);
    $node = $this->nodeStorage->load($nid);

    foreach ($status as $key => $expected_status) {
      if ($key == 0) {
        // Key 0 is the original, so we just check $node.
        $this->assertEquals($expected_status, $node->isPublished(),
          sprintf('%s: The original content (%s) is %s', $description, $this->languages[$key]['name'], ($expected_status ? 'published' : 'unpublished')));
      }
      else {
        // Key > 0 are the translations.
        $translation = $node->getTranslation($this->languages[$key]['code']);
        $this->assertEquals($expected_status, $translation->isPublished(),
          sprintf('%s: Translation %d (%s) is %s', $description, $key, $this->languages[$key]['name'], ($expected_status ? 'published' : 'unpublished')));
      }
    }
  }

  /**
   * Test creating translations with independent scheduling.
   *
   * @dataProvider dataPublishingTranslations()
   */
  public function testPublishingTranslations($publish_on_translatable, $unpublish_on_translatable, $status_translatable, array $expected_status_values_before, array $expected_status_values_after) {
    // Show the languages, this is for info and debug only.
    $this->drupalGet('admin/config/regional/language');

    // Set the scheduler fields to be translatable yes/no depending on the
    // parameters passed in.
    $this->drupalGet('admin/config/regional/content-language');
    $settings = [
      'edit-settings-node-' . $this->type . '-settings-language-language-alterable' => TRUE,
      'edit-settings-node-' . $this->type . '-fields-publish-on' => $publish_on_translatable,
      'edit-settings-node-' . $this->type . '-fields-unpublish-on' => $unpublish_on_translatable,
      'edit-settings-node-' . $this->type . '-fields-status' => $status_translatable,
    ];
    // The submit shows the updated values, so no need for second get.
    $this->submitForm($settings, 'Save configuration');

    $early_return = FALSE;
    if ($publish_on_translatable <> $status_translatable) {
      // Check for validation form error on status and publish_on.
      $this->assertSession()->elementExists('xpath', '//input[@id = "edit-settings-node-' . $this->type . '-fields-publish-on" and contains(@class, "error")]');
      $this->assertSession()->elementExists('xpath', '//input[@id = "edit-settings-node-' . $this->type . '-fields-status" and contains(@class, "error")]');
      $early_return = TRUE;
    }
    if ($unpublish_on_translatable <> $status_translatable) {
      // Check for validation form error on status and unpublish_on.
      $this->assertSession()->elementExists('xpath', '//input[@id = "edit-settings-node-' . $this->type . '-fields-unpublish-on" and contains(@class, "error")]');
      $this->assertSession()->elementExists('xpath', '//input[@id = "edit-settings-node-' . $this->type . '-fields-status" and contains(@class, "error")]');
      $early_return = TRUE;
    }
    if ($early_return) {
      // The rest of the test is meaningless so skip it and move to the next.
      return;
    }

    // Create a node in the 'original' language, before any translations. It is
    // unpublished with no scheduled date.
    $create = [
      'type' => $this->type,
      'title' => $this->languages[0]['name'] . '(0) - Unpublished and not scheduled',
      'langcode' => $this->languages[0]['code'],
      'status' => FALSE,
    ];
    $node = $this->drupalCreateNode($create);
    $nid = $node->id();

    // Create the first translation, published now with no scheduled dates.
    $this->drupalGet('node/' . $nid . '/translations/add/' . $this->languages[0]['code'] . '/' . $this->languages[1]['code']);
    $edit = [
      'title[0][value]' => $this->languages[1]['name'] . '(1) - Published now',
      'publish_on[0][value][date]' => '',
      'publish_on[0][value][time]' => '',
      'status[value]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    // Create second translation, for publishing and unpublising in the future.
    $this->drupalGet('node/' . $nid . '/translations/add/' . $this->languages[0]['code'] . '/' . $this->languages[2]['code']);
    $edit = [
      'title[0][value]' => $this->languages[2]['name'] . '(2) - Publish in the future',
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+2 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('+2 day', $this->requestTime)),
      'unpublish_on[0][value][date]' => date('Y-m-d', strtotime('+3 day', $this->requestTime)),
      'unpublish_on[0][value][time]' => date('H:i:s', strtotime('+3 day', $this->requestTime)),
    ];
    $this->submitForm($edit, 'Save');

    // Reset the cache, reload the node, and check if the dates of translation
    // 3 have been synchronized to the other translations, or not, as required.
    $this->nodeStorage->resetCache([$nid]);
    $node = $this->nodeStorage->load($nid);
    $translation1 = $node->getTranslation($this->languages[1]['code']);
    $translation2 = $node->getTranslation($this->languages[2]['code']);
    if ($publish_on_translatable) {
      $this->assertNotEquals($translation2->publish_on->value, $node->publish_on->value, 'Node publish_on');
      $this->assertNotEquals($translation2->unpublish_on->value, $node->unpublish_on->value, 'Node unpublish_on');
    }
    else {
      $this->assertEquals($translation2->publish_on->value, $node->publish_on->value, 'Node publish_on');
      $this->assertEquals($translation2->unpublish_on->value, $node->unpublish_on->value, 'Node unpublish_on');
      $this->assertEquals($translation2->publish_on->value, $translation1->publish_on->value, 'Translation 1 publish_on');
      $this->assertEquals($translation2->unpublish_on->value, $translation1->unpublish_on->value, 'Translation 1 unpublish_on');
    }

    // Create the third translation, to be published in the past.
    $this->drupalGet('node/' . $nid . '/translations/add/' . $this->languages[0]['code'] . '/' . $this->languages[3]['code']);
    $edit = [
      'title[0][value]' => $this->languages[3]['name'] . '(3) - Publish in the past',
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('-2 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('-2 day', $this->requestTime)),
    ];
    $this->submitForm($edit, 'Save');

    // Reset the cache, reload the node, and check if the dates of translation
    // 3 have been synchronized to the other translations, or not, as required.
    $this->nodeStorage->resetCache([$nid]);
    $node = $this->nodeStorage->load($nid);
    $translation1 = $node->getTranslation($this->languages[1]['code']);
    $translation2 = $node->getTranslation($this->languages[2]['code']);
    $translation3 = $node->getTranslation($this->languages[3]['code']);
    if ($publish_on_translatable) {
      $this->assertNotEquals($translation3->publish_on->value, $translation2->publish_on->value, 'Node publish_on');
      $this->assertNotEquals($translation3->unpublish_on->value, $translation2->unpublish_on->value, 'Node unpublish_on');
    }
    else {
      // The scheduer dates should be synchronized across all translations.
      $this->assertEquals($translation3->publish_on->value, $node->publish_on->value, 'Node publish_on');
      $this->assertEquals($translation3->unpublish_on->value, $node->unpublish_on->value, 'Node unpublish_on');
      $this->assertEquals($translation3->publish_on->value, $translation1->publish_on->value, 'Translation 1 publish_on');
      $this->assertEquals($translation3->unpublish_on->value, $translation1->unpublish_on->value, 'Translation 1 unpublish_on');
      $this->assertEquals($translation3->publish_on->value, $translation2->publish_on->value, 'Translation 2 publish_on');
      $this->assertEquals($translation3->unpublish_on->value, $translation2->unpublish_on->value, 'Translation 2 unpublish_on');
    }

    // For info only.
    $this->drupalGet($this->languages[0]['code'] . '/node/' . $nid . '/translations');
    $this->drupalGet('admin/content/scheduled');

    // Check that the status of all four pieces of content before running cron
    // match the expected values.
    $this->checkStatus($nid, 'Before cron', $expected_status_values_before);

    // Check that the status after running cron matches the expected values.
    $this->cronRun();
    $this->checkStatus($nid, 'After cron', $expected_status_values_after);

    // For info only.
    $this->drupalGet('admin/content/scheduled');
    $this->drupalGet('admin/content');
    $this->drupalGet('admin/reports/dblog');
    $this->drupalGet($this->languages[0]['code'] . '/node/' . $nid . '/translations');
  }

  /**
   * Provides data for testPublishingTranslations().
   *
   * Case 1 when the date is translatable and can differ between translations.
   * Case 2 when the date is not translatable and the behavior should be
   *   consistent over all translations.
   *
   * @return array
   *   The test data. Each array element has the format:
   *   Publish_on translatable
   *   Unublish_on translatable
   *   Status translatable
   *   Expected status of four translations before cron
   *   Expected status of four translations after cron
   */
  public function dataPublishingTranslations() {
    // The key text relates to which fields are translatable.
    return [
      'all fields' => [TRUE, TRUE, TRUE,
        [FALSE, TRUE, FALSE, FALSE],
        [FALSE, TRUE, FALSE, TRUE],
      ],

      'no fields' => [FALSE, FALSE, FALSE,
        [FALSE, FALSE, FALSE, FALSE],
        [TRUE, TRUE, TRUE, TRUE],
      ],
      'only publish_on' => [TRUE, FALSE, FALSE, [], []],
      'only unpublish_on' => [FALSE, TRUE, FALSE, [], []],
      'only status' => [FALSE, FALSE, TRUE, [], []],
      'publish_on and unpublish_on' => [TRUE, TRUE, FALSE, [], []],
      'publish_on and status' => [TRUE, FALSE, TRUE, [], []],
      'unpublish_on and status' => [FALSE, TRUE, TRUE, [], []],
    ];
  }

}
