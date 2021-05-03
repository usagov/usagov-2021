<?php

namespace Drupal\workbench_email\Plugin\RecipientType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\workbench_email\Plugin\RecipientTypeBase;
use Drupal\workbench_email\TemplateInterface;
use Drupal\workbench_moderation\ModerationInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a recipient type of an email field.
 *
 * @RecipientType(
 *   id = "email",
 *   title = @Translation("Email field"),
 *   description = @Translation("Send to email addresses in email field."),
 *   settings = {
 *     "fields" = {},
 *   },
 * )
 */
class EmailField extends RecipientTypeBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Moderation info service.
   *
   * @var \Drupal\workbench_moderation\ModerationInformationInterface|\Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a new EmailField object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\workbench_moderation\ModerationInformationInterface|\Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   Moderation info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, $moderation_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->has('workbench_moderation.moderation_information') ? $container->get('workbench_moderation.moderation_information') : $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Add the fields.
    $fields = $this->entityFieldManager->getFieldMapByFieldType('email');
    $field_options = [];
    foreach ($fields as $entity_type_id => $entity_type_fields) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$this->isModeratableEntityType($entity_type)) {
        // These fields are irrelevant, the entity type isn't moderated.
        continue;
      }
      $base = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      foreach ($entity_type_fields as $field_name => $field_detail) {
        if (in_array($field_name, array_keys($base), TRUE)) {
          continue;
        }
        $sample_bundle = reset($field_detail['bundles']);
        $sample_field = $this->entityTypeManager->getStorage('field_config')
          ->load($entity_type_id . '.' . $sample_bundle . '.' . $field_name);
        if ($sample_field) {
          $field_options[$entity_type_id . ':' . $field_name] = $sample_field->label() . ' (' . $entity_type->getLabel() . ')';
        }
      }
    }
    return [
      'fields' => [
        '#type' => 'checkboxes',
        '#title' => $this->t('Email Fields'),
        '#description' => $this->t('Send to mail address found in the selected fields'),
        '#options' => $field_options,
        '#default_value' => $this->getFields(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setFields(array_values(array_filter($form_state->getValue('fields'))));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    $recipients = [];
    $fields = array_filter($this->getFields(), function ($field_name) use ($entity) {
      list($entity_type, $field_name) = explode(':', $field_name, 2);
      return $entity_type === $entity->getEntityTypeId() && $entity->hasField($field_name) && !$entity->{$field_name}->isEmpty();
    });
    foreach ($fields as $field) {
      list(, $field_name) = explode(':', $field, 2);
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      foreach ($entity->{$field_name} as $field_item) {
        $recipients[] = $this->getEmailFromFieldItem($field_item);
      }
    }
    return $recipients;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailFromFieldItem(FieldItemInterface $field_item) {
    return $field_item->get('value')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    foreach ($this->getFields() as $identifier) {
      list ($entity_type_id, $field_name) = explode(':', $identifier, 2);
      if ($field = $field_storage->load("$entity_type_id.$field_name")) {
        $dependencies[$field->getConfigDependencyKey()][] = $field->getConfigDependencyName();
      }
    }
    return NestedArray::mergeDeep($dependencies, parent::calculateDependencies());
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $removed_fields = array_reduce($dependencies['config'], function (array $carry, $item) {
      if (!$item instanceof FieldStorageConfigInterface) {
        return $carry;
      }
      $carry[] = sprintf('%s:%s', $item->getTargetEntityTypeId(), $item->getName());
      return $carry;
    }, []);
    if ($removed_fields && array_intersect($removed_fields, $this->getFields())) {
      $this->setFields(array_diff($this->getFields(), $removed_fields));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets value of roles.
   *
   * @return array
   *   Value of roles
   */
  protected function getFields() {
    return $this->getConfiguration()['settings']['fields'];
  }

  /**
   * Sets roles.
   *
   * @param array $fields
   *   Field IDs in {entity_type}:{field_name} format.
   *
   * @return $this
   */
  protected function setFields(array $fields) {
    $configuration = $this->getConfiguration();
    $configuration['settings']['fields'] = $fields;
    $this->setConfiguration($configuration);
    return $this;
  }

  /**
   * Determines if an entity type has been marked as moderatable.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type object.
   *
   * @return bool
   *   TRUE if this entity type has been marked as moderatable, FALSE otherwise.
   */
  protected function isModeratableEntityType(EntityTypeInterface $entity_type) {
    if (method_exists($this->moderationInfo, 'isModeratableEntityType')) {
      return $this->moderationInfo->isModeratableEntityType($entity_type);
    }
    else {
      return $this->moderationInfo->canModerateEntitiesOfEntityType($entity_type);
    }
  }

}
