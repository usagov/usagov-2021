<?php

namespace Drupal\Tests\autosave_form\FunctionalJavascript\ContentEntity;

use Drupal\node\Entity\NodeType;

/**
 * Tests the autosave support for entity forms.
 *
 * @group autosave_form
 */
class NodeAutosaveFormTest extends ContentEntityAutosaveFormTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node'];

  /**
   * The entity type to be tested.
   *
   * @var string
   */
  protected $entityType = 'node';

  /**
   * The bundle of the entity type to be tested.
   *
   * @var string
   */
  protected $bundle = 'article';

  /**
   * {@inheritdoc}
   */
  protected function prepareSetUp() {
    // Create the desired node type.
    if (NodeType::load($this->bundle) === NULL) {
      $this->drupalCreateContentType(['type' => $this->bundle]);
    }
    parent::prepareSetUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function getCreateNewEntityURL() {
    return "node/add/{$this->bundle}";
  }

  /**
   * {@inheritdoc}
   */
  protected function getUserPermissions() {
    return ["create {$this->bundle} content", "edit own {$this->bundle} content"];
  }

}
