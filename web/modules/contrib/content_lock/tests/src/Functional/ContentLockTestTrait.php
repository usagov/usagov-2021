<?php

namespace Drupal\Tests\content_lock\Functional;

use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Trait ContentLockTestTrait.
 */
trait ContentLockTestTrait {

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * User without break lock permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user1;

  /**
   * User with break lock permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user2;

  /**
   * The entity to test.
   *
   * @var \Drupal\entity_test\Entity\EntityTestMul
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $additional_permissions = [];
    if (\Drupal::moduleHandler()->moduleExists('content_translation')) {
      $additional_permissions = [
        'administer languages',
        'administer content translation',
        'create content translations',
        'update content translations',
        'delete content translations',
        'translate any entity',
      ];
    }

    $this->admin = $this->drupalCreateUser(array_merge([
      'administer entity_test content',
      'administer content lock',
    ], $additional_permissions));

    $this->user1 = $this->drupalCreateUser(array_merge([
      'view test entity',
      'administer entity_test content',
    ], $additional_permissions));
    $this->user2 = $this->drupalCreateUser(array_merge([
      'view test entity',
      'administer entity_test content',
      'break content lock',
    ], $additional_permissions));

    if (\Drupal::moduleHandler()->moduleExists('content_translation')) {
      ConfigurableLanguage::create(['id' => 'de'])->save();
      $this->drupalLogin($this->admin);
      $this->drupalGet('admin/config/regional/content-language');
      $edit = [
        'entity_types[entity_test_mul_changed]' => 'entity_test_mul_changed',
        'settings[entity_test_mul_changed][entity_test_mul_changed][translatable]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][name]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][created]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][user_id]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][field_test_text]' => 1,
      ];
      $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));
      $this->rebuildContainer();
    }

    $this->entity = EntityTestMulChanged::create([
      'name' => $this->randomMachineName(),
    ]);
    $this->entity->save();
  }

}
