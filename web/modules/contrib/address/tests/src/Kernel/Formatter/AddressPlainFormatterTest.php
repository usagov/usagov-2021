<?php

namespace Drupal\Tests\address\Kernel\Formatter;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests the address_plain formatter.
 *
 * @group address
 */
class AddressPlainFormatterTest extends FormatterTestBase {

  /**
   * The entity with an address field to test formatting with.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createField('address', 'address_plain');

    $this->entity = EntityTest::create([]);
    $this->entity->{$this->fieldName} = [
      'country_code' => 'AD',
      'locality' => 'Canillo',
      'postal_code' => 'AD500',
      'address_line1' => 'C. Prat de la Creu, 62-64',
    ];

  }

  /**
   * Tests the rendered output.
   */
  public function testRender() {
    $this->renderEntityFields($this->entity, $this->display);

    // Confirm the expected elements, including the predefined locality
    // (properly escaped), country name.
    $expected_elements = [
      'C. Prat de la Creu, 62-64',
      'AD500',
      'Canillo',
      'Andorra',
    ];
    foreach ($expected_elements as $expected_element) {
      $this->assertRaw($expected_element);
    }

    // Confirm that an unrecognized locality is shown unmodified.
    $this->entity->{$this->fieldName}->locality = 'FAKE_LOCALITY';
    $this->renderEntityFields($this->entity, $this->display);
    $this->assertRaw('FAKE_LOCALITY');
  }

  /**
   * Tests the theme hook suggestions.
   *
   * @see \Drupal\Tests\node\Functional\NodeTemplateSuggestionsTest
   */
  public function testAddressPlainThemeHookSuggestions() {
    foreach (['full', 'my_custom_view_mode'] as $view_mode) {
      // Simulate themeing of the address test entity.
      $variables['theme_hook_original'] = 'address_plain';
      $variables['view_mode'] = $view_mode;
      $variables['address'] = $this->entity->{$this->fieldName};
      $suggestions = \Drupal::moduleHandler()->invokeAll('theme_suggestions_address_plain', [$variables]);

      $expected_suggestions = [
        // Hook __ entity_type __ view_mode.
        'address_plain__entity_test__' . $view_mode,
        // Hook __ entity_type __ bundle.
        'address_plain__entity_test__entity_test',
        // Hook __ entity_type __ bundle __ view_mode.
        'address_plain__entity_test__entity_test__' . $view_mode,
        // Hook __ field_name.
        'address_plain__' . $this->fieldName,
        // Hook __ entity_type __ field_name.
        'address_plain__entity_test__' . $this->fieldName,
        // Hook __ entity_type __ field_name __ bundle.
        'address_plain__entity_test__' . $this->fieldName . '__entity_test',
      ];
      $this->assertEquals($expected_suggestions, $suggestions, 'Unexpected theme suggestions for ' . $view_mode);
    }
  }

}
