<?php

namespace Drupal\dynamic_entity_reference\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if referenced entities are valid.
 */
class ValidDynamicReferenceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\dynamic_entity_reference\SelectionPluginManager
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ValidReferenceConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The selection plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.dynamic_entity_reference_selection'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\dynamic_entity_reference\Plugin\Validation\Constraint\ValidDynamicReferenceConstraint $constraint */
    /** @var \Drupal\Core\Field\FieldItemListInterface $value */
    if (!isset($value)) {
      return;
    }

    // Collect new entities, IDs and target types of existing entities across
    // the field items.
    $new_entities = [];
    $target_ids = [];
    $target_types = [];
    $valid_target_types = DynamicEntityReferenceItem::getTargetTypes($value->getFieldDefinition()->getSettings());
    foreach ($value as $delta => $item) {
      // We don't use a regular NotNull constraint for the target_id property as
      // NULL is allowed if the entity property contains an unsaved entity.
      // @see \Drupal\Core\TypedData\DataReferenceTargetDefinition::getConstraints()
      if (!$item->isEmpty() && ($item->target_id === NULL || $item->target_type === NULL)) {
        $property = $item->target_id ? 'target_type' : 'target_id';
        if (!$item->entity->isNew()) {
          $this->context->buildViolation($constraint->nullMessage)
            ->setParameter('%property', $property)
            ->atPath((string) $delta . '.' . $property)
            ->addViolation();
          continue;
        }
        $new_entities[$delta] = $item->entity;
      }

      // Validate target type.
      $valid_type = empty($item->target_type) || (!empty($item->target_type) && DynamicEntityReferenceItem::entityHasIntegerId($item->target_type) && in_array($item->target_type, $valid_target_types));
      if (!$valid_type) {
        // Remove new entity if the target type is not valid.
        if (isset($new_entities[$delta])) {
          unset($new_entities[$delta]);
        }
        $this->context->buildViolation($constraint->wrongTypeMessage)
          ->setParameter('%type', $item->target_type)
          ->atPath((string) $delta . '.target_type')
          ->setInvalidValue($item->target_type)
          ->addViolation();
        continue;
      }

      // '0' or NULL are considered valid empty references.
      if (!empty($item->target_id)) {
        $target_ids[$delta] = $item->target_id;
      }
      $target_types[$delta] = $item->target_type;
    }

    // Collect all new and created valid entities fot bundle validation.
    $valid_entities = [];
    $entity = !empty($value->getParent()) ? $value->getEntity() : NULL;
    // Validate new entities.
    foreach ($new_entities as $delta => $new_entity) {
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = $this->selectionManager->getSelectionHandler($value->getFieldDefinition(), $entity, $target_types[$delta]);
      if ($handler instanceof SelectionWithAutocreateInterface && ($new_entity->getEntityTypeId() == $target_types[$delta] || empty($target_types[$delta]))) {
        if (!$handler->validateReferenceableNewEntities([$new_entity])) {
          $this->context->buildViolation($constraint->invalidAutocreateMessage)
            ->setParameter('%type', $new_entity->getEntityTypeId())
            ->setParameter('%label', $new_entity->label())
            ->atPath((string) $delta . '.entity')
            ->setInvalidValue($new_entity)
            ->addViolation();
          continue;
        }
      }
      $valid_entities[$delta] = $new_entity;
    }

    // Validate id for non existent entities.
    foreach ($target_ids as $delta => $target_id) {
      $definition = $this->entityTypeManager->getDefinition($target_types[$delta]);
      $existing_id = $this->entityTypeManager->getStorage($target_types[$delta])
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($definition->getKey('id'), $target_id)
        ->execute();
      if (!$existing_id) {
        $this->context->buildViolation($constraint->nonExistingMessage)
          ->setParameter('%type', $target_types[$delta])
          ->setParameter('%id', $target_id)
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
        unset($target_ids[$delta]);
        continue;
      }
      $valid_entities[$delta] = $this->entityTypeManager->getStorage($target_types[$delta])->load($target_id);
    }

    // Validate bundles.
    foreach ($valid_entities as $delta => $entity) {
      if (array_key_exists('target_bundles', $value->getFieldDefinition()->getSetting($target_types[$delta])['handler_settings'])) {
        $valid_bundle = FALSE;
        $target_bundles = $value->getFieldDefinition()->getSetting($target_types[$delta])['handler_settings']['target_bundles'];
        if ($target_bundles === []) {
          $this->context->buildViolation($constraint->noBundleAllowed)
            ->setParameter('%type', $target_types[$delta])
            ->atPath((string) $delta . '.entity')
            ->setInvalidValue($entity)
            ->addViolation();
          if (isset($target_ids[$delta])) {
            unset($target_ids[$delta]);
          }
          continue;
        }
        if ($target_bundles === NULL || in_array($entity->bundle(), $target_bundles)) {
          $valid_bundle = TRUE;
        }
        if (!$valid_bundle) {
          $this->context->buildViolation($constraint->wrongBundleMessage)
            ->setParameter('%label', $entity->label())
            ->setParameter('%bundles', implode(', ', $target_bundles))
            ->atPath((string) $delta . '.entity')
            ->setInvalidValue($entity)
            ->addViolation();
          if (isset($target_ids[$delta])) {
            unset($target_ids[$delta]);
          }
        }
      }
    }

    // Validate target ids.
    foreach ($target_ids as $delta => $id) {
      $handler = $this->selectionManager->getSelectionHandler($value->getFieldDefinition(), $entity, $target_types[$delta]);
      $valid_target_id = $handler->validateReferenceableEntities([$id]);
      if (!$valid_target_id) {
        $this->context->buildViolation($constraint->message)
          ->setParameter('%type', $target_types[$delta])
          ->setParameter('%id', $id)
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($id)
          ->addViolation();
      }
    }
  }

}
