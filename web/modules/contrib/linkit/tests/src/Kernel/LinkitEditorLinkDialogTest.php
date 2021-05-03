<?php

namespace Drupal\Tests\linkit\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\editor\Entity\Editor;
use Drupal\editor\Form\EditorLinkDialog;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\filter\Entity\FilterFormat;
use Drupal\linkit\SubstitutionManagerInterface;
use Drupal\linkit\Tests\ProfileCreationTrait;

/**
 * Tests EditorLinkDialog validation and conversion functionality.
 *
 * @group linkit
 */
class LinkitEditorLinkDialogTest extends LinkitKernelTestBase {

  use ProfileCreationTrait;

  /**
   * The linkit profile.
   *
   * @var \Drupal\linkit\ProfileInterface
   */
  protected $linkitProfile;

  /**
   * Editor for testing.
   *
   * @var \Drupal\editor\EditorInterface
   */
  protected $editor;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['editor', 'ckeditor', 'entity_test'];

  /**
   * Sets up the test.
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installSchema('system', ['key_value_expire']);

    // Create a profile.
    $this->linkitProfile = $this->createProfile();

    /** @var \Drupal\linkit\MatcherManager $matcherManager */
    $matcherManager = $this->container->get('plugin.manager.linkit.matcher');

    // Add the entity_test matcher to the profile.
    /** @var \Drupal\linkit\MatcherInterface $plugin */
    $plugin = $matcherManager->createInstance('entity:entity_test');
    $this->linkitProfile->addMatcher($plugin->getConfiguration());
    $this->linkitProfile->save();

    // Add a text format.
    $format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => [],
    ]);
    $format->save();

    // Set up editor.
    $this->editor = Editor::create([
      'format' => 'filtered_html',
      'editor' => 'ckeditor',
    ]);
    $this->editor->setSettings([
      'plugins' => [
        'drupallink' => [
          'linkit_enabled' => TRUE,
          'linkit_profile' => $this->linkitProfile->id(),
        ],
      ],
    ]);
    $this->editor->save();
  }

  /**
   * Tests adding a link.
   */
  public function testAdd() {
    $entity_label = $this->randomString();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = EntityTest::create(['name' => $entity_label]);
    $entity->save();

    $form_object = new EditorLinkDialog();

    $input = [
      'editor_object' => [],
      'dialogOptions' => [
        'title' => 'Add Link',
        'dialogClass' => 'editor-link-dialog',
        'autoResize' => 'true',
      ],
      '_drupal_ajax' => '1',
      'ajax_page_state' => [
        'theme' => 'bartik',
        'theme_token' => 'some-token',
        'libraries' => '',
      ],
    ];
    $form_state = (new FormState())
      ->setRequestMethod('POST')
      ->setUserInput($input)
      ->addBuildInfo('args', [$this->editor]);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    $this->assertEquals('linkit.autocomplete', $form['attributes']['href']['#autocomplete_route_name'], 'Linkit is enabled on the linkit field.');
    $this->assertEmpty($form['attributes']['href']['#default_value'], 'The linkit field is empty.');

    $form_state->setValue(['attributes', 'href'], 'https://example.com/');
    $form_state->setValue('href_dirty_check', '');
    $form_state->setValue(['attributes', 'data-entity-type'], $this->randomString());
    $form_state->setValue(['attributes', 'data-entity-uuid'], $this->randomString());
    $form_state->setValue(['attributes', 'data-entity-substitution'], $this->randomString());
    $form_builder->submitForm($form_object, $form_state);
    $this->assertEmpty($form_state->getValue(['attributes', 'data-entity-type']));
    $this->assertEmpty($form_state->getValue(['attributes', 'data-entity-uuid']));
    $this->assertEmpty($form_state->getValue(['attributes', 'data-entity-substitution']));

    $entity_url = $entity->toUrl('canonical', ['path_processing' => FALSE])->toString();
    $form_state->setValue(['attributes', 'href'], $entity_url);
    $form_state->setValue('href_dirty_check', $entity_url);
    $form_state->setValue(['attributes', 'data-entity-type'], $entity->getEntityTypeId());
    $form_state->setValue(['attributes', 'data-entity-uuid'], $entity->uuid());
    $form_state->setValue(['attributes', 'data-entity-substitution'], SubstitutionManagerInterface::DEFAULT_SUBSTITUTION);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertEquals($entity->getEntityTypeId(), $form_state->getValue(['attributes', 'data-entity-type']), 'Attribute "data-entity-type" exists and has the correct value.');
    $this->assertEquals($entity->uuid(), $form_state->getValue(['attributes', 'data-entity-uuid']), 'Attribute "data-entity-uuid" exists and has the correct value.');
    $this->assertEquals(SubstitutionManagerInterface::DEFAULT_SUBSTITUTION, $form_state->getValue(['attributes', 'data-entity-substitution']), 'Attribute "data-entity-substitution" exists and has the correct value.');
  }

  /**
   * Tests editing a link with data attributes.
   */
  public function testEditWithDataAttributes() {
    $entity_label = $this->randomString();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = EntityTest::create(['name' => $entity_label]);
    $entity->save();
    $entity_url = $entity->toUrl('canonical', ['path_processing' => FALSE])->toString();

    $form_object = new EditorLinkDialog();

    $input = [
      'editor_object' => [
        'href' => $entity_url,
        'data-entity-type' => $entity->getEntityTypeId(),
        'data-entity-uuid' => $entity->uuid(),
        'data-entity-substitution' => SubstitutionManagerInterface::DEFAULT_SUBSTITUTION,
      ],
      'dialogOptions' => [
        'title' => 'Edit Link',
        'dialogClass' => 'editor-link-dialog',
        'autoResize' => 'true',
      ],
      '_drupal_ajax' => '1',
      'ajax_page_state' => [
        'theme' => 'bartik',
        'theme_token' => 'some-token',
        'libraries' => '',
      ],
    ];
    $form_state = (new FormState())
      ->setRequestMethod('POST')
      ->setUserInput($input)
      ->addBuildInfo('args', [$this->editor]);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    $this->assertEquals('linkit.autocomplete', $form['attributes']['href']['#autocomplete_route_name'], 'Linkit is enabled on the href field.');
    $this->assertEquals($entity_url, $form['attributes']['href']['#default_value'], 'The href field has the url as default value.');
    $this->assertEquals($entity->getEntityTypeId(), $form_state->getValue(['attributes', 'data-entity-type']), 'Attribute "data-entity-type" exists and has the correct value.');
    $this->assertEquals($entity->uuid(), $form_state->getValue(['attributes', 'data-entity-uuid']), 'Attribute "data-entity-uuid" exists and has the correct value.');
    $this->assertEquals(SubstitutionManagerInterface::DEFAULT_SUBSTITUTION, $form_state->getValue(['attributes', 'data-entity-substitution']), 'Attribute "data-entity-substitution" exists and has the correct value.');
  }

  /**
   * Tests editing a link without data attributes.
   */
  public function testEditWithoutDataAttributes() {
    $form_object = new EditorLinkDialog();

    $input = [
      'editor_object' => [
        'href' => 'http://example.com/',
      ],
      'dialogOptions' => [
        'title' => 'Edit Link',
        'dialogClass' => 'editor-link-dialog',
        'autoResize' => 'true',
      ],
      '_drupal_ajax' => '1',
      'ajax_page_state' => [
        'theme' => 'bartik',
        'theme_token' => 'some-token',
        'libraries' => '',
      ],
    ];
    $form_state = (new FormState())
      ->setRequestMethod('POST')
      ->setUserInput($input)
      ->addBuildInfo('args', [$this->editor]);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    $this->assertEquals('linkit.autocomplete', $form['attributes']['href']['#autocomplete_route_name'], 'Linkit is enabled on the href field.');
    $this->assertEquals('http://example.com/', $form['attributes']['href']['#default_value'], 'The href field default value is the external URI.');
    $this->assertEmpty($form['attributes']['data-entity-type']['#default_value']);
    $this->assertEmpty($form['attributes']['data-entity-uuid']['#default_value']);
    $this->assertEmpty($form['attributes']['data-entity-substitution']['#default_value']);
  }

}
