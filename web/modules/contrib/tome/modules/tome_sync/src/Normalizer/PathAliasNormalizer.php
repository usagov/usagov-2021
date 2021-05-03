<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;

/**
 * Normalizer for Path alias entities.
 *
 * @internal
 */
class PathAliasNormalizer extends ContentEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = '\Drupal\path_alias\PathAliasInterface';

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a PathAliasNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeRepositoryInterface $entity_type_repository = NULL, EntityFieldManagerInterface $entity_field_manager = NULL, EntityRepositoryInterface $entity_repository) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = parent::normalize($object, $format, $context);
    foreach (['path', 'alias'] as $key) {
      if (!empty($attributes[$key][0]['value'])) {
        $parts = explode('/', $attributes[$key][0]['value']);
        if (count($parts) >= 3 && $this->entityTypeManager->hasDefinition($parts[1]) && is_numeric($parts[2])) {
          if ($entity = $this->entityTypeManager->getStorage($parts[1])->load($parts[2])) {
            $parts[2] = $entity->uuid();
            $attributes[$key][0]['value'] = implode('/', $parts);
          }
        }
      }
    }
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    /** @var \Drupal\path_alias\PathAliasInterface $entity */
    $entity = parent::denormalize($data, $class, $format, $context);
    foreach (['path', 'alias'] as $key) {
      if ($entity && !empty($entity->get($key)->value)) {
        $parts = explode('/', $entity->get($key)->value);
        if (count($parts) >= 3 && $this->entityTypeManager->hasDefinition($parts[1]) && Uuid::isValid($parts[2])) {
          if ($referenced_entity = $this->entityRepository->loadEntityByUuid($parts[1], $parts[2])) {
            $parts[2] = $referenced_entity->id();
            $entity->set($key, implode('/', $parts));
          }
        }
      }
    }
    return $entity;
  }

}
