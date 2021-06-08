<?php

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldType;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\dynamic_entity_reference\DataDynamicReferenceDefinition;

/**
 * Defines the 'dynamic_entity_reference' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - exclude_entity_types: Allow user to include or exclude entity_types.
 * - entity_type_ids: The entity type ids that can or cannot be referenced.
 *
 * @property int target_id
 * @property string target_type
 * @property \Drupal\Core\Entity\ContentEntityInterface entity
 *
 * @FieldType(
 *   id = "dynamic_entity_reference",
 *   label = @Translation("Dynamic entity reference"),
 *   description = @Translation("An entity field containing a dynamic entity reference."),
 *   category = @Translation("Dynamic Reference"),
 *   no_ui = FALSE,
 *   list_class = "\Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList",
 *   default_widget = "dynamic_entity_reference_default",
 *   default_formatter = "dynamic_entity_reference_label"
 * )
 */
class DynamicEntityReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'exclude_entity_types' => TRUE,
      'entity_type_ids' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $default_settings = [];
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = $labels[(string) t('Content', [], ['context' => 'Entity type group'])];
    // Field storage settings are not accessible here so we are assuming that
    // all the entity types are referenceable by default.
    // See https://www.drupal.org/node/2346273#comment-9385179 for more details.
    foreach (array_keys($options) as $entity_type_id) {
      $default_settings[$entity_type_id] = [
        'handler' => "default:$entity_type_id",
        'handler_settings' => [],
      ];
    }
    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $properties['target_type'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target Entity Type'))
      ->setRequired(TRUE);

    $properties['entity'] = DataDynamicReferenceDefinition::create('entity')
      ->setLabel(t('Entity'))
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'target_id' => [
        'description' => 'The ID of the target entity.',
        'type' => 'int',
        'unsigned' => TRUE,
      ],
      'target_type' => [
        'description' => 'The Entity Type ID of the target entity.',
        'type' => 'varchar',
        'length' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      ],
    ];

    $schema = [
      'columns' => $columns,
      'indexes' => [
        'target_id' => ['target_id', 'target_type'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    /** @var \Drupal\dynamic_entity_reference\Plugin\DataType\DynamicEntityReference $entity_property */
    $entity_property = $this->get('entity');
    if ($property_name == 'target_type' && !$entity_property->getValue()) {
      $entity_property->getTargetDefinition()->setEntityTypeId($this->get('target_type')->getValue());
    }
    // Make sure that the target type and the target property stay in sync.
    elseif ($property_name == 'entity') {
      $this->writePropertyValue('target_type', $entity_property->getValue()->getEntityTypeId());
    }
    elseif ($property_name == 'target_id') {
      // Just in case target_id is set before target_type then set it to empty
      // string instead of NULL so that
      // \Drupal\Core\Entity\Plugin\DataType\EntityReference::setValue
      // doesn't throw "InvalidArgumentException: Value is not a valid entity".
      $entity_property->getTargetDefinition()->setEntityTypeId($this->get('target_type')->getValue() ?: '');
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   *
   * To select both target_type and target_id the option value is
   * changed from target_id to target_type-target_id.
   *
   * @see \Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceOptionsTrait::massageFormValues()
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    $field_definition = $this->getFieldDefinition();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_bundles_info = \Drupal::service('entity_type.bundle.info');
    $selection_manager = \Drupal::service('plugin.manager.dynamic_entity_reference_selection');
    $options = [];
    $settings = $this->getSettings();
    $target_types = static::getTargetTypes($settings);
    foreach ($target_types as $target_type) {
      $options[$target_type] = $selection_manager->getSelectionHandler($field_definition, $this->getEntity(), $target_type)->getReferenceableEntities();
    }
    if (empty($options)) {
      return [];
    }
    $return = [];
    foreach ($options as $target_type => $referenceable_entities) {
      $target_type_info = $entity_type_manager->getDefinition($target_type);
      $target_type_label = $target_type_info->getLabel();
      // Rebuild the array by changing the bundle key into the bundle label.
      $bundles = $entity_type_bundles_info->getBundleInfo($target_type);
      foreach ($referenceable_entities as $bundle => $entities) {
        // The label does not need sanitizing since it is used as an optgroup
        // which is only supported by select elements and auto-escaped.
        $bundle_label = $bundles[$bundle]['label'];
        foreach ($entities as $id => $entity_label) {
          if (count($target_types) > 1) {
            if ($target_type_info->hasKey('bundle')) {
              $return[(string) $target_type_label . ': ' . (string) $bundle_label]["{$target_type}-{$id}"] = "$entity_label ($target_type_label:$id)";
            }
            else {
              $return[(string) $target_type_label]["{$target_type}-{$id}"] = "$entity_label ($target_type_label:$id)";
            }
          }
          else {
            $return[(string) $bundle_label]["{$target_type}-{$id}"] = "$entity_label ($target_type_label:$id)";
          }
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    // @todo inject this.
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = $labels[(string) t('Content', [], ['context' => 'Entity type group'])];
    foreach (array_keys($options) as $entity_type_id) {
      if (!static::entityHasIntegerId($entity_type_id)) {
        unset($options[$entity_type_id]);
      }
    }
    $element['exclude_entity_types'] = [
      '#type' => 'checkbox',
      '#title' => t('Exclude the selected items'),
      '#default_value' => $this->getSetting('exclude_entity_types'),
      '#disabled' => $has_data,
    ];

    $element['entity_type_ids'] = [
      '#type' => 'select',
      '#title' => t('Select items'),
      '#options' => $options,
      '#default_value' => $this->getSetting('entity_type_ids'),
      '#disabled' => $has_data,
      '#multiple' => TRUE,
      '#element_validate' => [
        [DynamicEntityReferenceItem::class, 'storageSettingsFormValidate'],
      ],
    ];

    return $element;
  }

  /**
   * Form element validation for storage settings.
   *
   * @param array $element
   *   The form element .
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form.
   */
  public static function storageSettingsFormValidate(array &$element, FormStateInterface $form_state, array $form) {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = array_filter(array_keys($labels[(string) t('Content', [], ['context' => 'Entity type group'])]), function ($entity_type_id) {
      return static::entityHasIntegerId($entity_type_id);
    });
    $exclude_entity_types = $form_state->getValue([
      'settings',
      'exclude_entity_types',
    ], 0);
    $entity_type_ids = $form_state->getValue([
      'settings',
      'entity_type_ids',
    ], []);
    $diff = array_diff($options, $entity_type_ids);
    if ((!$exclude_entity_types && empty($entity_type_ids)) || ($exclude_entity_types && empty($diff))) {
      $form_state->setError($element, t('Select at least one entity type ID.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    $settings_form = [];
    $settings = $this->getSettings();
    foreach (static::getTargetTypes($settings) as $target_type) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($target_type);
      $settings_form[$target_type] = $this->targetTypeFieldSettingsForm($form, $form_state, $target_type);
      $settings_form[$target_type]['handler']['#title'] = t('Reference type for @target_type', ['@target_type' => $entity_type->getLabel()]);
    }
    return $settings_form;
  }

  /**
   * Returns a form for single target type settings.
   *
   * This is same as
   * \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::fieldSettingsForm()
   * but it uses dynamic_entity_reference_selection plugin manager instead of
   * entity_reference_selection plugin manager.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   * @param string $target_type
   *   The target entity type id.
   *
   * @return array
   *   The form definition for the field settings.
   */
  protected function targetTypeFieldSettingsForm(array $form, FormStateInterface $form_state, $target_type) {
    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = $form_state->getFormObject()->getEntity();
    $field_settings = $field->getSettings();
    /** @var \Drupal\dynamic_entity_reference\SelectionPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.dynamic_entity_reference_selection');
    // Get all selection plugins for this entity type.
    $selection_plugins = $manager->getSelectionGroups($target_type);
    $handlers_options = [];
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
      }
      elseif (array_key_exists($selection_group_id . ':' . $target_type, $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $target_type;
        $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
      }
    }

    $form = [
      '#type' => 'container',
      '#process' => [[EntityReferenceItem::class, 'fieldSettingsAjaxProcess']],
      '#element_validate' => [[
        DynamicEntityReferenceItem::class,
        'fieldSettingsFormValidate',
      ],
      ],
    ];
    $form['handler'] = [
      '#type' => 'details',
      '#title' => t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[EntityReferenceItem::class, 'formProcessMergeParent']],
    ];

    $form['handler']['handler'] = [
      '#type' => 'select',
      '#title' => t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $field_settings[$target_type]['handler'],
      '#required' => TRUE,
      '#ajax' => TRUE,
      '#limit_validation_errors' => [],
    ];
    $form['handler']['handler_submit'] = [
      '#type' => 'submit',
      '#value' => t('Change handler'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => ['entity_reference_settings_ajax_submit'],
    ];

    $form['handler']['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity_reference-settings']],
    ];

    $handler = $manager->getSelectionHandler($field, NULL, $target_type);
    $form['handler']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);

    return $form;
  }

  /**
   * Form element validation handler; Stores the new values in the form state.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\field\Entity\FieldConfig $field */
    $field = $form_state->getFormObject()->getEntity();
    foreach (static::getTargetTypes($field->getSettings()) as $target_type) {
      // If no checkboxes were checked for 'target_bundles', store NULL ("all
      // bundles are referenceable") rather than empty array ("no bundle is
      // referenceable" - typically happens when all referenceable bundles have
      // been deleted).
      if ($form_state->getValue([
        'settings',
        $target_type,
        'handler_settings',
        'target_bundles',
      ]) === []) {
        $form_state->setValue([
          'settings',
          $target_type,
          'handler_settings',
          'target_bundles',
        ], NULL);
      }
      // Don't store the 'target_bundles_update' button value into the field
      // config settings.
      $form_state->unsetValue([
        'settings',
        $target_type,
        'handler_settings',
        'target_bundles_update',
      ]);
      $form_state->unsetValue([
        'settings',
        $target_type,
        'handler_submit',
      ]);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    // Dynamic entity reference field has two main properties i.e. target_type
    // and target_id but for entity field query to evaluate the relationship
    // specifier correctly the main property is needed. It is also needed to
    // render the correct field value in views.
    return 'target_id';
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // If either a scalar or an object was passed as the value for the item,
    // assign it to the 'entity' property since that works for both cases.
    if (isset($values) && !is_array($values)) {
      $this->set('entity', $values, $notify);
    }
    else {
      if ((empty($values['target_type']) && !empty($values['target_id'])) && !(isset($values['entity']) && $values['entity'] instanceof EntityInterface)) {
        throw new \InvalidArgumentException('No entity type was provided, value is not a valid entity.');
      }
      // We have to bypass the EntityReferenceItem::setValue() here because we
      // also want to invoke onChange for target_type.
      FieldItemBase::setValue($values, FALSE);
      // Support setting the field item with only one property, but make sure
      // values stay in sync if only property is passed.
      // NULL is a valid value, so we use array_key_exists().
      if (is_array($values) && array_key_exists('target_id', $values) && array_key_exists('target_type', $values) && !isset($values['entity'])) {
        $this->onChange('target_type', FALSE);
        $this->onChange('target_id', FALSE);
      }
      elseif (is_array($values) && !array_key_exists('target_id', $values) && !array_key_exists('target_type', $values) && isset($values['entity'])) {
        $this->onChange('entity', FALSE);
      }
      // If both properties are passed, verify the passed values match. The
      // only exception we allow is when we have a new entity: in this case
      // its actual id and target_id will be different, due to the new entity
      // marker.
      elseif (is_array($values) && array_key_exists('target_id', $values) && array_key_exists('target_type', $values) && isset($values['entity'])) {
        /** @var \Drupal\dynamic_entity_reference\Plugin\DataType\DynamicEntityReference $entity_property */
        $entity_property = $this->get('entity');
        $entity_id = $entity_property->getTargetIdentifier();
        /** @var \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface $target_definition */
        $target_definition = $entity_property->getTargetDefinition();
        $entity_type = $target_definition->getEntityTypeId();
        if (!$this->entity->isNew() && $values['target_id'] !== NULL && (($entity_id !== $values['target_id']) || ($entity_type !== $values['target_type']))) {
          throw new \InvalidArgumentException('The target id, target type and entity passed to the dynamic entity reference item do not match.');
        }
      }
      // Notify the parent if necessary.
      if ($notify && $this->parent) {
        $this->parent->onChange($this->getName());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = parent::getValue();
    if (!empty($values['target_type'])) {
      $this->get('entity')->getTargetDefinition()->setEntityTypeId($values['target_type']);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Avoid loading the entity by first checking the 'target_id'.
    if ($this->target_id !== NULL && $this->target_type !== NULL) {
      return FALSE;
    }
    if ($this->entity && $this->entity instanceof EntityInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if ($this->hasNewEntity()) {
      // Save the entity if it has not already been saved by some other code.
      if ($this->entity->isNew()) {
        $this->entity->save();
      }
      // Make sure the parent knows we are updating this property so it can
      // react properly.
      $this->target_id = $this->entity->id();
      $this->target_type = $this->entity->getEntityTypeId();
    }
    if (!$this->isEmpty() && $this->target_id === NULL) {
      $this->target_id = $this->entity->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    /** @var \Drupal\dynamic_entity_reference\SelectionPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.dynamic_entity_reference_selection');
    $settings = $field_definition->getSettings();
    foreach (static::getTargetTypes($settings) as $target_type) {
      $values['target_type'] = $target_type;
      if ($referenceable = $manager->getSelectionHandler($field_definition, NULL, $target_type)->getReferenceableEntities()) {
        $group = array_rand($referenceable);
        $values['target_id'] = array_rand($referenceable[$group]);
        return $values;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    $dependencies = FieldItemBase::calculateDependencies($field_definition);
    $entity_repository = \Drupal::service('entity.repository');
    if ($default_value = $field_definition->getDefaultValueLiteral()) {
      foreach ($default_value as $value) {
        if (is_array($value) && isset($value['target_uuid']) && isset($value['target_type'])) {
          $entity = $entity_repository->loadEntityByUuid($value['target_type'], $value['target_uuid']);
          // If the entity does not exist do not create the dependency.
          // @see \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList::processDefaultValue()
          if ($entity) {
            $dependencies[$entity->getEntityType()->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
          }
        }
      }
    }
    // Depend on target bundle configurations. Dependencies for 'target_bundles'
    // also covers the 'auto_create_bundle' setting, if any, because its value
    // is included in the 'target_bundles' list.
    $entity_type_manager = \Drupal::entityTypeManager();
    $settings = $field_definition->getSettings();
    foreach (static::getTargetTypes($settings) as $target_type) {
      $handler = $settings[$target_type]['handler_settings'];
      if (!empty($handler['target_bundles'])) {
        $target_entity_type = $entity_type_manager->getDefinition($target_type);
        if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
          if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
            foreach ($storage->loadMultiple($handler['target_bundles']) as $bundle) {
              $dependencies[$bundle->getConfigDependencyKey()][] = $bundle->getConfigDependencyName();
            }
          }
        }
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function onDependencyRemoval(FieldDefinitionInterface $field_definition, array $dependencies) {
    $changed = FALSE;
    if ($default_value = $field_definition->getDefaultValueLiteral()) {
      foreach ($default_value as $key => $value) {
        if (is_array($value) && isset($value['target_uuid']) && isset($value['target_type'])) {
          $entity = \Drupal::service('entity.repository')->loadEntityByUuid($value['target_type'], $value['target_uuid']);
          // @see \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList::processDefaultValue()
          if ($entity && isset($dependencies[$entity->getConfigDependencyKey()][$entity->getConfigDependencyName()])) {
            unset($default_value[$key]);
            $changed = TRUE;
          }
        }
      }
      if ($changed) {
        $field_definition->setDefaultValue($default_value);
      }
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    // Update the 'target_bundles' handler setting if a bundle config dependency
    // has been removed.
    $settings = $field_definition->getSettings();
    foreach (static::getTargetTypes($settings) as $target_type) {
      $bundles_changed = FALSE;
      $handler_settings = $settings[$target_type]['handler_settings'];
      if (!empty($handler_settings['target_bundles'])) {
        $target_entity_type = $entity_type_manager->getDefinition($target_type);
        if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
          if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
            foreach ($storage->loadMultiple($handler_settings['target_bundles']) as $bundle) {
              if (isset($dependencies[$bundle->getConfigDependencyKey()][$bundle->getConfigDependencyName()])) {
                unset($handler_settings['target_bundles'][$bundle->id()]);

                // If this bundle is also used in the 'auto_create_bundle'
                // setting, disable the auto-creation feature completely.
                $auto_create_bundle = !empty($handler_settings['auto_create_bundle']) ? $handler_settings['auto_create_bundle'] : FALSE;
                if ($auto_create_bundle && $auto_create_bundle == $bundle->id()) {
                  $handler_settings['auto_create'] = FALSE;
                  $handler_settings['auto_create_bundle'] = NULL;
                }

                $bundles_changed = TRUE;

                // In case we deleted the only target bundle allowed by the
                // field we have to log a critical message because the field
                // will not function correctly anymore.
                if ($handler_settings['target_bundles'] === []) {
                  \Drupal::logger('dynamic_entity_reference')
                    ->critical('The %target_bundle bundle (entity type: %target_entity_type) was deleted. As a result, the %field_name dynamic entity reference field (entity_type: %entity_type, bundle: %bundle) no longer has any valid bundle it can reference. The field is not working correctly anymore and has to be adjusted.', [
                      '%target_bundle' => $bundle->label(),
                      '%target_entity_type' => $bundle->getEntityType()
                        ->getBundleOf(),
                      '%field_name' => $field_definition->getName(),
                      '%entity_type' => $field_definition->getTargetEntityTypeId(),
                      '%bundle' => $field_definition->getTargetBundle(),
                    ]);
                }
              }
            }
          }
        }
      }
      if ($bundles_changed) {
        $settings[$target_type]['handler_settings'] = $handler_settings;
        $field_definition->setSettings($settings);
      }
      $changed |= $bundles_changed;
    }
    return $changed;
  }

  /**
   * Helper function to get all the entity type ids that can be referenced.
   *
   * @param array $settings
   *   The settings of the field storage.
   *
   * @return string[]
   *   All the target entity type ids that can be referenced.
   */
  public static function getTargetTypes(array $settings) {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = array_filter(array_keys($labels[(string) t('Content', [], ['context' => 'Entity type group'])]), function ($entity_type_id) {
      return static::entityHasIntegerId($entity_type_id);
    });

    if (!empty($settings['exclude_entity_types'])) {
      return array_diff($options, $settings['entity_type_ids'] ?: []);
    }
    else {
      return array_intersect($options, $settings['entity_type_ids'] ?: []);
    }
  }

  /**
   * Determines if an entity type has an integer-based ID definition.
   *
   * @param string $entity_type_id
   *   The ID the represents the entity type.
   *
   * @return bool
   *   Returns TRUE if the entity type has an integer-based ID definition and
   *   FALSE otherwise.
   */
  public static function entityHasIntegerId($entity_type_id) {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    // Make sure entity type is a content entity type.
    if (!($entity_type instanceof ContentEntityTypeInterface)) {
      return FALSE;
    }
    // Make sure entity type has an id.
    if (!$entity_type->hasKey('id')) {
      return FALSE;
    }
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($entity_type_id);
    $entity_type_id_definition = $field_definitions[$entity_type->getKey('id')];
    return $entity_type_id_definition->getType() === 'integer';
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateStorageDependencies(FieldStorageDefinitionInterface $field_definition) {
    $dependencies = FieldItemBase::calculateStorageDependencies($field_definition);
    $entity_manager = \Drupal::entityTypeManager();
    foreach (static::getTargetTypes($field_definition->getSettings()) as $entity_type_id) {
      if ($entity_manager->hasDefinition($entity_type_id) && $target_entity_type = $entity_manager->getDefinition($entity_type_id)) {
        $dependencies['module'][] = $target_entity_type->getProvider();
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    $options = [];

    // Add all the commonly referenced entity types as distinct pre-configured
    // options.
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    $common_references = array_filter($entity_types, function (EntityTypeInterface $entity_type) {
      return $entity_type->isCommonReferenceTarget();
    });

    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    foreach ($common_references as $entity_type) {
      $options[$entity_type->id()] = [
        'label' => $entity_type->getLabel(),
        'field_storage_config' => [
          'settings' => [
            'exclude_entity_types' => FALSE,
            'entity_type_ids' => [$entity_type->id()],
          ],
        ],
      ];
    }

    return $options;
  }

}
