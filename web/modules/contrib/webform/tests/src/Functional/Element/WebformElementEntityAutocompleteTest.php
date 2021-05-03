<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\webform\Entity\Webform;

/**
 * Tests for entity automcomplete element.
 *
 * @group webform
 */
class WebformElementEntityAutocompleteTest extends WebformElementBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['filter', 'user', 'node', 'taxonomy', 'webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_entity_autocomplete'];

  /**
   * Test entity reference elements.
   */
  public function testEntityReferenceTest() {
    $node_1 = $this->drupalCreateNode(['title' => 'node 01']);
    $node_2 = $this->drupalCreateNode(['title' => 'node 02']);

    $vocabulary = Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);
    $vocabulary->save();
    $term_1 = Term::create([
      'name' => 'term 01',
      'vid' => 'tags',
    ]);
    $term_1->save();

    /**************************************************************************/

    $webform = Webform::load('test_element_entity_autocomplete');

    // Check render entity_autocomplete.
    $this->drupalGet('/webform/test_element_entity_autocomplete');
    $this->assertFieldByName('entity_autocomplete_user_default', 'admin (1)');
    $this->assertFieldByName('entity_autocomplete_user_tags', 'admin (1)');
    $this->assertFieldByName('entity_autocomplete_user_multiple[items][0][_item_]', 'admin (1)');
    $this->assertFieldByName('entity_autocomplete_node_default', $node_1->label() . ' (' . $node_1->id() . ')');
    $this->assertFieldByName('entity_autocomplete_term_tags_autocreate', $term_1->label() . ' (' . $term_1->id() . ')');
    $this->assertFieldByName('entity_autocomplete_term_multiple_autocreate[items][0][_item_]', $term_1->label() . ' (' . $term_1->id() . ')');

    // Check process entity_autocomplete.
    $this->postSubmission($webform);
    $this->assertRaw("entity_autocomplete_user_default: '1'
entity_autocomplete_user_tags:
  - '1'
entity_autocomplete_user_multiple:
  - '1'
entity_autocomplete_node_default: '1'
entity_autocomplete_term_tags_autocreate:
  - '1'
entity_autocomplete_term_multiple_autocreate:
  - '1'");

    // Check term auto create.
    $edit = [
      'entity_autocomplete_term_tags_autocreate' => 'term 02',
      'entity_autocomplete_term_multiple_autocreate[items][0][_item_]' => 'term 03',
      'entity_autocomplete_term_multiple_autocreate[items][1][_item_]' => 'term 04',
    ];
    $this->postSubmission($webform, $edit);
    $this->assertRaw("entity_autocomplete_term_tags_autocreate:
  - '2'
entity_autocomplete_term_multiple_autocreate:
  - '3'
  - '4'");
  }

}
