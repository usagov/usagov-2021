<?php

namespace Drupal\scanner\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\scanner\Plugin\ScannerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring the default scanner settings.
 */
class ScannerAdminForm extends ConfigFormBase {

  /**
   * The scanner plugin manager.
   *
   * @var \Drupal\scanner\Plugin\ScannerPluginManager
   */
  protected $scannerPluginManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Holds the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity_type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a ScannerAdminForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\scanner\Plugin\ScannerPluginManager $scanner_plugin_manager
   *   The scanner plugin manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle info service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ScannerPluginManager $scanner_plugin_manager,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($config_factory);
    $this->scannerPluginManager = $scanner_plugin_manager;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.scanner'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scanner_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['scanner.admin_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('scanner.admin_settings');

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default options'),
    ];
    $form['settings']['scanner_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: Case sensitive search mode'),
      '#default_value' => $config->get('scanner_mode'),
    ];
    $form['settings']['scanner_wholeword'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: Match whole word'),
      '#default_value' => $config->get('scanner_wholeword'),
    ];
    $form['settings']['scanner_regex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: Regular expression search'),
      '#default_value' => $config->get('scanner_regex'),
    ];
    $form['settings']['scanner_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: Search published nodes only'),
      '#default_value' => $config->get('scanner_published'),
    ];
    $form['settings']['scanner_pathauto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: Maintain custom aliases'),
      '#default_value' => $config->get('scanner_pathauto'),
    ];

    $langs = $this->getLanguages();
    $form['settings']['scanner_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Default: Content language'),
      '#options' => $langs,
      '#default_value' => $config->get('scanner_language'),
    ];

    $available_entity_types = $this->getAvailableEntityTypes();
    $form['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled entity types'),
      '#options' => $available_entity_types,
      '#default_value' => $config->get('enabled_content_types'),
      '#description' => $this->t('Third party plugins can be written to add more options here.'),
      '#ajax' => [
        'callback' => [$this, 'getFieldsCallback'],
        'event' => 'change',
        'wrapper' => 'content-type-fields',
      ],
    ];

    $enabled_entity_types = $form_state->getValue('enabled_content_types');
    if ($enabled_entity_types === NULL) {
      $enabled_entity_types = $this->config('scanner.admin_settings')->get('enabled_content_types');
    }

    $fields = $this->getEntityTypeFields($available_entity_types, $enabled_entity_types);

    $form['fields_of_selected_content_type'] = [
      '#title' => $this->t('Enabled fields'),
      '#type' => 'checkboxes',
      '#options' => $fields,
      '#default_value' => $config->get('fields_of_selected_content_type'),
      '#prefix' => '<div id="content-type-fields">',
      '#suffix' => '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('scanner.admin_settings')
      ->set('scanner_mode', $form_state->getValue('scanner_mode'))
      ->set('scanner_regex', $form_state->getValue('scanner_regex'))
      ->set('scanner_wholeword', $form_state->getValue('scanner_wholeword'))
      ->set('scanner_published', $form_state->getValue('scanner_published'))
      ->set('scanner_pathauto', $form_state->getValue('scanner_pathauto'))
      ->set('scanner_language', $form_state->getValue('scanner_language'))
      ->set('enabled_content_types', array_filter($form_state->getValue('enabled_content_types')))
      ->set('fields_of_selected_content_type', array_filter($form_state->getValue('fields_of_selected_content_type')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback for fetching the entity type fields.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   List of entity type fields.
   */
  public function getFieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['fields_of_selected_content_type'];
  }

  /**
   * Gets a list of available entity types as input options.
   *
   * @return array
   *   An array containing the entity type options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getAvailableEntityTypes() {
    $options = [];

    // Iterate over the available plugins to get their 'types'.
    foreach ($this->scannerPluginManager->getDefinitions() as $definition) {
      $entity_type_id = $definition['type'];
      try {
        $entity_type = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType();
      }
      catch (PluginNotFoundException $ignored) {
        // Non-existent entity was provided. Ignore it.
        continue;
      }
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $bundle) {
        $options["$entity_type_id:$bundle_id"] = $this->t('@entity_type » @bundle', [
          '@entity_type' => $entity_type->getLabel(),
          '@bundle' => $bundle['label'],
        ]);
      }
    }

    return $options;
  }

  /**
   * Gets a list of entity fields as input options.
   *
   * @param array $available_entity_types
   *   List of available entity types.
   * @param array $entity_types
   *   The entity types, with their relevant bundles.
   *
   * @return array
   *   An array containing the fields of the entity types.
   */
  protected function getEntityTypeFields(array $available_entity_types, array $entity_types) {
    $options = [];

    // Iterate through each of the selected entity types and get their fields.
    foreach ($entity_types as $key => $value) {
      if (empty($value) || !isset($available_entity_types[$key])) {
        // Ignore the entity type if it's unticked
        // or the entity type no longer exists.
        continue;
      }
      list($entity_type, $bundle) = explode(':', $key);
      // Get the fields for the given entity type and bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      foreach ($field_definitions as $field_name => $field_definition) {
        $allowed_field_type = [
          // TODO:codebymikey:20200210 why no string_long?
          'string', 'text_with_summary', 'text', 'text_long',
        ];
        // We are only interested in certain field types.
        if (in_array($field_definition->getType(), $allowed_field_type, TRUE)) {
          // Skip fields starting with "parent_" (Paragraphs internal fields).
          if (strpos($field_name, 'parent_') === 0) {
            continue;
          }
          $name_with_type = "$entity_type:$bundle:$field_name";
          $options[$name_with_type] = $this->t('@entity_bundle » @field_label <small><strong>(@field_name)</strong></small>', [
            '@entity_bundle' => $available_entity_types[$key],
            '@field_label' => $field_definition->getLabel(),
            '@field_name' => $field_name,
          ]);
        }
      }
    }

    return $options;
  }

  /**
   * Gets a list of languages as input options.
   *
   * @return array
   *   An array containing the list of enabled languages.
   */
  protected function getLanguages() {
    $languages = $this->languageManager->getLanguages();
    $items = [
      'all' => $this->t('All'),
    ];
    foreach ($languages as $language) {
      $items[$language->getId()] = $language->getName();
    }

    return $items;
  }

}
