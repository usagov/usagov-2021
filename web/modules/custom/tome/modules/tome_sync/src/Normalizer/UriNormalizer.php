<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\serialization\Normalizer\PrimitiveDataNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalizer for Uri data.
 *
 * @internal
 */
class UriNormalizer extends PrimitiveDataNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = '\Drupal\Core\TypedData\Type\UriInterface';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a UriNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $value = parent::normalize($object, $format, $context);
    if (!empty($value) && strpos($value, 'entity:') === 0) {
      $parts = explode('/', str_replace('entity:', '', $value));
      if (count($parts) >= 2 && $this->entityTypeManager->hasDefinition($parts[0]) && is_numeric($parts[1])) {
        if ($entity = $this->entityTypeManager->getStorage($parts[0])->load($parts[1])) {
          $parts[1] = $entity->uuid();
          $value = 'entity:' . implode('/', $parts);
        }
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $type, $format = NULL, array $context = []) {
    if (!empty($data) && strpos($data, 'entity:') === 0) {
      $parts = explode('/', str_replace('entity:', '', $data));
      if (count($parts) >= 2 && $this->entityTypeManager->hasDefinition($parts[0]) && Uuid::isValid($parts[1])) {
        if ($referenced_entity = $this->entityRepository->loadEntityByUuid($parts[0], $parts[1])) {
          $parts[1] = $referenced_entity->id();
          $data = 'entity:' . implode('/', $parts);
        }
      }
    }
    return $data;
  }

}
