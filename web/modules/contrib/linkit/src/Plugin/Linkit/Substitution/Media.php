<?php

namespace Drupal\linkit\Plugin\Linkit\Substitution;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\linkit\SubstitutionInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A substitution plugin for the URL to a file.
 *
 * @Substitution(
 *   id = "media",
 *   label = @Translation("Direct URL to media file entity"),
 * )
 */
class Media extends PluginBase implements SubstitutionInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    $url = new GeneratedUrl();

    if (!($entity instanceof MediaInterface)) {
      return $url;
    }

    $source_field = $entity->getSource()->getSourceFieldDefinition($entity->get('bundle')->entity);
    if ($source_field && $entity->hasField($source_field->getName()) && $entity->get($source_field->getName())->entity instanceof FileInterface) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $entity->get($source_field->getName())->entity;
      $url->setGeneratedUrl(file_create_url($file->getFileUri()));
      $url->addCacheableDependency($entity);
      return $url;
    }

    // If available, fall back to the canonical URL if the bundle doesn't have
    // a file source field.
    if ($entity->getEntityType()->getLinkTemplate('canonical') != $entity->getEntityType()->getLinkTemplate('edit-form')) {
      return $entity->toUrl('canonical')->toString(TRUE);
    }

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements('Drupal\media\MediaInterface');
  }

}
