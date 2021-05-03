<?php

namespace Drupal\workbench_email\Update;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workbench_email\TemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for update helpers.
 */
class UpdateHelper implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ConfigEntityUpdater constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Update helper for migrating from old configuration to recipient plugins.
   *
   * @param \Drupal\workbench_email\TemplateInterface $template
   *   Template being updated.
   *
   * @return bool
   *   TRUE if updates were made.
   */
  public static function updateToRecipientPlugin(TemplateInterface $template) {
    $plugins = [];
    if ($template->get('author')) {
      $plugins['author'] = [
        'id' => 'author',
        'provider' => 'workbench_email',
        'status' => TRUE,
        'settings' => [],
      ];
    }
    if ($roles = $template->get('roles')) {
      $plugins['role'] = [
        'id' => 'role',
        'provider' => 'workbench_email',
        'status' => TRUE,
        'settings' => [
          'roles' => $roles,
        ],
      ];
    }
    if ($fields = $template->get('fields')) {
      $plugins['email'] = [
        'id' => 'email',
        'provider' => 'workbench_email',
        'status' => TRUE,
        'settings' => [
          'fields' => $fields,
        ],
      ];
    }
    $template->set('recipient_types', $plugins);
    $template->set('fields', NULL);
    $template->set('roles', NULL);
    $template->set('author', NULL);
    return TRUE;
  }

  /**
   * Updates template entities in Drupal core < 8.6.
   *
   * @param array $sandbox
   *   Stores information for batch updates.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   When the entity type is not found.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   When the entity type is not found.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If an error occurs during update.
   *
   * @see workbench_email_post_update_move_to_recipient_plugins()
   */
  public function legacyUpdateToRecipientPlugin(array &$sandbox) {
    $storage = $this->entityTypeManager->getStorage('workbench_email_template');
    $sandbox_key = 'config_entity_updater:workbench_email_template';
    if (!isset($sandbox[$sandbox_key])) {
      $sandbox[$sandbox_key]['entities'] = $storage->getQuery()->accessCheck(FALSE)->execute();
      $sandbox[$sandbox_key]['count'] = count($sandbox[$sandbox_key]['entities']);
    }

    /** @var \Drupal\workbench_email\TemplateInterface $template */
    $entities = $storage->loadMultiple(array_splice($sandbox[$sandbox_key]['entities'], 0, 50));
    foreach ($entities as $template) {
      if (self::updateToRecipientPlugin($template)) {
        $template->trustData();
        $template->save();
      }
    }

    $sandbox['#finished'] = empty($sandbox[$sandbox_key]['entities']) ? 1 : ($sandbox[$sandbox_key]['count'] - count($sandbox[$sandbox_key]['entities'])) / $sandbox[$sandbox_key]['count'];
  }

}
