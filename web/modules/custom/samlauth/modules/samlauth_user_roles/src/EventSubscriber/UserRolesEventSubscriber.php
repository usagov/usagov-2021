<?php

namespace Drupal\samlauth_user_roles\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for the samlauth_user_roles module.
 */
class UserRolesEventSubscriber implements EventSubscriberInterface {

  /**
   * Name of the configuration object containing the setting used by this class.
   */
  const CONFIG_OBJECT_NAME = 'samlauth_user_roles.mapping';

  /**
   * The configuration factory service.
   *
   * We're doing $configFactory->get() all over the place to access our
   * configuration, which (despite its convoluted-ness) is actually a little
   * more efficient than storing the config object in a variable in this class.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new SamlauthUsersyncEventSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SamlauthEvents::USER_SYNC][] = ['onUserSync'];
    return $events;
  }

  /**
   * Assigns/unassigns roles as needed during user sync.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The event being dispatched.
   */
  public function onUserSync(SamlauthUserSyncEvent $event) {
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    if ($config->get('only_first_login') && !$event->isFirstLogin()) {
      return;
    }

    /** @var \Drupal\user\Entity\Role[] $valid_roles */
    $valid_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($valid_roles[UserInterface::ANONYMOUS_ROLE]);
    unset($valid_roles[UserInterface::AUTHENTICATED_ROLE]);
    $account = $event->getAccount();
    $changed_role_ids = $account_role_ids = $account->getRoles();

    // Remove 'unassign' roles, then add 'default' roles to $changed_role_ids.
    $role_names = $config->get('unassign_roles');
    if ($role_names) {
      if (is_array($role_names)) {
        $changed_role_ids = array_diff(
          $changed_role_ids,
          $this->getRoleIds($role_names, $valid_roles, 'unassign_roles')
        );
      }
      else {
        // Spam logs until configuration is fixed.
        $this->logger->warning('Invalid %name configuration value; skipping role unassignment.', ['%name' => 'unassign_roles']);
      }
    }

    $role_names = $config->get('default_roles');
    if ($role_names) {
      if (is_array($role_names)) {
        $changed_role_ids = array_unique(array_merge(
          $changed_role_ids,
          $this->getRoleIds($role_names, $valid_roles, 'default_roles')
        ));
      }
      else {
        $this->logger->warning('Invalid %name configuration value; skipping part of role assignment.', ['%name' => 'default_roles']);
      }
    }

    // Process role mapping. Spam logs about anything strange in the
    // attribute values or value_map configuration. (We may need to iterate on
    // the logs for attribute values, because they don't mention the associated
    // account. It's possible that the account has no ID and no name yet.)
    $attribute_name = $config->get('saml_attribute');
    $value_map = $config->get('value_map');
    if ($attribute_name) {
      if ($value_map && is_array($value_map)) {
        $separator = $config->get('saml_attribute_separator');

        // We don't differentiate between several 'IdP role' values
        // concatenated in one attribute value, a multi-value attribute or a
        // combination of both. Get all 'IdP role' values into one array.
        $idp_role_values = [];
        $attributes = $event->getAttributes();
        if (isset($attributes[$attribute_name])) {
          if (!is_array($attributes[$attribute_name])) {
            // We've never seen single-array string values for an attribute but
            // let's support them without complaining.
            if (is_string($attributes[$attribute_name])) {
              $attributes[$attribute_name] = [$attributes[$attribute_name]];
            }
            else {
              $this->logger->warning('%name attribute is not an array of values; this points to a coding error.', ['%name' => $attribute_name]);
            }
          }
          if (is_array($attributes[$attribute_name])) {
            foreach ($attributes[$attribute_name] as $attribute_value) {
              // "0" is a valid attribute value. "" / NULL are considered
              // 'empty / not a value' and 0 is... inconsequential.
              if ($attribute_value != NULL) {
                if (!is_string($attribute_value)) {
                  $this->logger->warning('%name attribute contains a (or multiple) non-string value(s); this points to a coding error.', ['%name' => $attribute_name]);
                }
                if ($separator) {
                  $idp_role_values = array_merge($idp_role_values, explode($separator, $attribute_value));
                }
                else {
                  $idp_role_values[] = $attribute_value;
                }
              }
            }
          }
        }

        if ($idp_role_values) {
          // Process values (add IDs of mapped roles); skip unknown values.
          foreach (array_map('trim', $idp_role_values) as $idp_role_value) {
            // The same IdP value can be mapped to multiple roles so loop
            // through all defined mappings. If we find any illegal
            // configuration, that could mean we log duplicate warnings.
            foreach ($value_map as $mapping) {
              if (isset($mapping['attribute_value'])) {
                if ($idp_role_value === $mapping['attribute_value']) {
                  // Attribute value matches role mapping.
                  if (isset($mapping['role_machine_name'])) {
                    if (isset($valid_roles[$mapping['role_machine_name']])) {
                      $changed_role_ids[] = $valid_roles[$mapping['role_machine_name']]->id();
                    }
                    else {
                      $this->logger->warning('Unknown/invalid role %role in %name configuration value; (partially?) skipping role assignment.', [
                        '%name' => 'value_map',
                        '%role' => $mapping['role_machine_name'],
                      ]);
                    }
                  }
                  else {
                    $this->logger->warning('%subname not present in %name configuration value; (partially?) skipping role assignment.', [
                      '%name' => 'value_map',
                      '%subname' => 'role_machine_name',
                    ]);
                  }
                }
              }
              else {
                $this->logger->warning('%subname not present in %name configuration value; role assignment may be partially skipped.', [
                  '%name' => 'value_map',
                  '%subname' => 'attribute_value',
                ]);
              }
            }
          }
          $changed_role_ids = array_unique($changed_role_ids);
        }
      }
      elseif (!is_array($value_map)) {
        $this->logger->warning('%name is not an array; skipping role mapping.', ['%name' => 'value_map']);
      }
      else {
        // We expect either both config values or neither to be set. Otherwise,
        // spam logs.
        $this->logger->warning('%name is not configured; skipping role mapping.', ['%name' => 'value_map']);
      }
    }
    elseif ($value_map && trim($value_map)) {
      $this->logger->warning('%name is not configured; skipping role mapping.', ['%name' => 'saml_attribute']);
    }

    sort($account_role_ids);
    sort($changed_role_ids);
    if ($changed_role_ids != $account_role_ids) {
      foreach (array_diff($account_role_ids, $changed_role_ids) as $role_id) {
        $account->removeRole($role_id);
      }
      foreach (array_diff($changed_role_ids, $account_role_ids) as $role_id) {
        $account->addRole($role_id);
      }
      $event->markAccountChanged();
    }
  }

  /**
   * Converts role machine names into role IDs; logs unknown names.
   *
   * @param array $role_names
   *   The role machine names to convert.
   * @param \Drupal\user\Entity\Role[] $valid_roles_by_name
   *   Array with all roles valid for this purpose.
   * @param string $config_log_name
   *   Name to use for warning log if applicable.
   */
  protected function getRoleIds(array $role_names, array $valid_roles_by_name, $config_log_name) {
    $role_ids = [];
    foreach ($role_names as $role_name) {
      if (isset($valid_roles_by_name[$role_name])) {
        $role_ids[] = $valid_roles_by_name[$role_name]->id();
      }
      else {
        $this->logger->warning('Unknown/invalid role %role in %name configuration value; skipping part of role (un)assignment.', [
          '%name' => $config_log_name,
          '%role' => $role_name,
        ]);
      }
    }

    return $role_ids;
  }

}
