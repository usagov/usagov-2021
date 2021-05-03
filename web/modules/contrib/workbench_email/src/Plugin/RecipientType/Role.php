<?php

namespace Drupal\workbench_email\Plugin\RecipientType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\RoleInterface;
use Drupal\workbench_email\Plugin\RecipientTypeBase;
use Drupal\workbench_email\TemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a recipient type of user role.
 *
 * @RecipientType(
 *   id = "role",
 *   title = @Translation("Role"),
 *   description = @Translation("Send to all users with selected roles."),
 *   settings = {
 *     "roles" = {},
 *   },
 * )
 */
class Role extends RecipientTypeBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Role object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $roles = array_filter($this->entityTypeManager->getStorage('user_role')
      ->loadMultiple(), function (RoleInterface $role) {
        return !in_array($role->id(), [
          RoleInterface::ANONYMOUS_ID,
          RoleInterface::AUTHENTICATED_ID,
        ], TRUE);
      });
    $role_options = array_map(function (RoleInterface $role) {
      return $role->label();
    }, $roles);
    return [
      'roles' => [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles'),
        '#description' => $this->t('Send to all users with selected roles'),
        '#options' => $role_options,
        '#default_value' => $this->getRoles(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setRoles(array_filter($form_state->getValue('roles')));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    $recipients = [];
    foreach ($this->getRoles() as $role) {
      foreach ($this->entityTypeManager->getStorage('user')->loadByProperties([
        'roles' => $role,
        'status' => 1,
      ]) as $account) {
        $recipients[] = $account->getEmail();
      }
    }
    return $recipients;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    foreach ($role_storage->loadMultiple($this->getRoles()) as $role) {
      $dependencies[$role->getConfigDependencyKey()][] = $role->getConfigDependencyName();
    }
    return NestedArray::mergeDeep($dependencies, parent::calculateDependencies());
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $removed_roles = array_reduce($dependencies['config'], function (array $carry, $item) {
      if (!$item instanceof RoleInterface) {
        return $carry;
      }
      $carry[] = $item->id();
      return $carry;
    }, []);
    if ($removed_roles && array_intersect($removed_roles, $this->getRoles())) {
      $this->setRoles(array_diff($this->getRoles(), $removed_roles));
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
  protected function getRoles() {
    return $this->getConfiguration()['settings']['roles'];
  }

  /**
   * Sets roles.
   *
   * @param array $roles
   *   Role IDs.
   *
   * @return $this
   */
  protected function setRoles(array $roles) {
    $configuration = $this->getConfiguration();
    $configuration['settings']['roles'] = $roles;
    $this->setConfiguration($configuration);
    return $this;
  }

}
