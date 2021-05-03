<?php

namespace Drupal\block_content_revision_ui;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds revision permissions for block content.
 */
class BlockContentRevisionUiPermissions implements ContainerInjectionInterface {

  /**
   * Block content type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentTypeStorage;

  /**
   * BlockContentRevisionUiPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $blockContentTypeStorage
   *   Block content type storage.
   */
  public function __construct(EntityStorageInterface $blockContentTypeStorage) {
    $this->blockContentTypeStorage = $blockContentTypeStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('block_content_type')
    );
  }

  /**
   * Generate dynamic permissions.
   */
  public function permissions() {
    $permissions = [];

    foreach ($this->blockContentTypeStorage->loadMultiple() as $bundle => $blockContentType) {
      assert($blockContentType instanceof BlockContentTypeInterface);
      $bundleLabel = $blockContentType->label();
      $permissions['view block_content ' . $bundle . ' history'] = [
        'title' => 'View ' . $bundleLabel . ' history pages',
      ];
      $permissions['view block_content ' . $bundle . ' revisions'] = [
        'title' => 'View ' . $bundleLabel . ' revisions pages',
      ];
      $permissions['revert block_content ' . $bundle . ' revisions'] = [
        'title' => 'Revert ' . $bundleLabel . ' revisions',
      ];
      $permissions['delete block_content ' . $bundle . ' revisions'] = [
        'title' => 'Delete ' . $bundleLabel . ' revisions',
      ];
    }

    return $permissions;
  }

}
