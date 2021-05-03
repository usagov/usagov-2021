<?php

namespace Drupal\linkit\Plugin\Linkit\Substitution;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\linkit\SubstitutionInterface;

/**
 * A substitution plugin for the URL to a file.
 *
 * @Substitution(
 *   id = "file",
 *   label = @Translation("Direct File URL"),
 * )
 */
class File extends PluginBase implements SubstitutionInterface {

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    $url = new GeneratedUrl();
    /** @var \Drupal\file\FileInterface $entity */
    $url->setGeneratedUrl(file_create_url($entity->getFileUri()));
    $url->addCacheableDependency($entity);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements('Drupal\file\FileInterface');
  }

}
