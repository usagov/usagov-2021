<?php

namespace Drupal\samlauth_user_roles\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\samlauth_user_roles\EventSubscriber\UserRolesEventSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains the samlauth user mapping form.
 */
class SamlauthRoleMappingForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for \Drupal\samlauth\Form\SamlauthUserMappingForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity field manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    // I'm using ConfigFormBase for the unified save button / message, but
    // don't want to use ConfigFormBase::config(), to keep a unified way of
    // getting config values in forms / not obfuscate call structures and get
    // confused later. So this method/value is unneeded, but ConfigFormBase
    // requires it. Let's make it empty.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'samlauth_user_roles_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get(UserRolesEventSubscriber::CONFIG_OBJECT_NAME);
    $roles = $this->getRoleLabels();

    $form['only_first_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only take action on first login'),
      '#description' => $this->t("Use the below settings once to set up the roles, but (unlike the default behavior) ignore them on subsequent logins."),
      '#default_value' => $config->get('only_first_login'),
    ];
    $state_disable_if_never = [
      'disabled' => [':input[name="role_actions"]' => ['value' => 0]],
    ];

    $form['unassign_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Unassign roles'),
      '#options' => $roles,
      '#description' => $this->t("Unassign these roles when applicable, before doing any assignments. Note that if we stop assigning a role to a certain user on login, that doesn't remove their (previously assigned) Drupal role unless it is selected here."),
      '#default_value' => $config->get('unassign_roles') ?: [],
      '#states' => $state_disable_if_never,
    ];

    $form['default_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Assign roles unconditionally'),
      '#options' => $roles,
      '#description' => $this->t('Selected roles will be assigned regardless of attribute values.'),
      '#default_value' => array_values($config->get('default_roles') ?: []),
      '#states' => $state_disable_if_never,
    ];

    $form['saml_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SAML attribute'),
      '#description' => $this->t('Name of the attribute whose value will be converted to Drupal user roles to be assigned.'),
      '#default_value' => $config->get('saml_attribute'),
      '#states' => $state_disable_if_never,
    ];

    $form['saml_attribute_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#description' => $this->t("If the IdP passes all role values concatenated into one attribute value rather than each in a separate attribute value, we'll use this string as the separator, to split the attribute back into individual 'IdP roles'."),
      '#default_value' => $config->get('saml_attribute_separator'),
      '#size' => 5,
      '#states' => $state_disable_if_never,
    ];

    // We store role machine names but use display names in the input. (If
    // someone wants to convert this into a multi-element input with select
    // elements, please go ahead.)
    $stored_values = $config->get('value_map');
    $display_values = [];
    if (is_array($stored_values)) {
      foreach ($stored_values as $mapping) {
        if (!is_array($mapping)
          || !isset($mapping['attribute_value']) || !is_string($mapping['attribute_value'])
          || !isset($mapping['role_machine_name']) || !is_string($mapping['role_machine_name'])) {
          $this->messenger->addWarning($this->t('Value mapping configuration is invalid and will be (partly?) wiped when a new mapping is added.'));
        }
        else {
          if (!isset($roles[$mapping['role_machine_name']])) {
            $this->messenger()->addWarning($this->t('Role with machine name %name (which is configured to be converted from IdP value %value) does not exist; the configuration form cannot be saved without rectifying this.', [
              '%name' => $mapping['role_machine_name'],
              '%value' => $mapping['attribute_value'],
            ]), TRUE);
          }
          $display_values[] = $mapping['attribute_value'] . '|' . ($roles[$mapping['role_machine_name']] ?? '');
        }
      }
    }
    $form['value_map'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value conversions'),
      '#description' => $this->t('One line of form [IdP role value]|[Drupal role name] for each possible value (case sensitive) that the IdP passes on. Values can be converted to multiple roles by copying them on multiple lines. If this is left empty, the values from the IdP will be interpreted as machine names for Drupal roles.'),
      '#default_value' => implode("\n", $display_values),
      '#states' => $state_disable_if_never,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value_map = $form_state->getValue('value_map');
    if ($value_map && trim($value_map)) {
      // Prepare: index $roles by display name.
      $roles[] = $duplicate_roles = [];
      foreach ($this->getRoleLabels() as $machine_name => $label) {
        if (!isset($duplicate_roles[$label])) {
          if (isset($roles[$label])) {
            $duplicate_roles[$label] = TRUE;
            unset($roles[$label]);
          }
          elseif ($label != '') {
            $roles[$label] = $machine_name;
          }
        }
      }

      // This partially duplicates code in UserRolesEventSubscriber, which does
      // the same checks on the configuration value while assigning roles.
      $errors = [];
      $lines = explode("\n", $value_map);
      foreach ($lines as $line) {
        if (trim($line)) {
          $parts = explode('|', $line);
          if (count($parts) == 1) {
            $errors[] = $this->t('Line contains only a value, not "value|role": @line.', ['@line' => $line]);
          }
          else {
            array_shift($parts);
            // Both the role and the IdP value can contain '|'; the only
            // restriction is that one and only one combination of $parts must
            // resolve to a role (display) name.
            $found = 0;
            while ($parts) {
              $label = trim(implode('|', $parts));
              // Role labels are also matched case sensitively, which should be
              // fine because all role labels are visible on screen - the user
              // can just copy/paste.
              if (isset($duplicate_roles[$label])) {
                $errors[] = $this->t('"@value" matches multiple roles; please rename your roles to be unique.', ['@value' => $label]);
                continue 2;
              }
              if (isset($roles[$label])) {
                $found++;
              }
              array_shift($parts);
            }
            if (!$found) {
              $errors[] = $this->t('No role found matching "@value".', ['@value' => $line]);
            }
            elseif ($found > 1) {
              // If this ever happens and the roles cannot be renamed, then the
              // only fix is to replace this multi-line input widget with
              // something else, or edit the configuration in another way.
              $errors[] = $this->t('Multiple roles found matching "@value". We unfortunately cannot work with this.', ['@value' => $line]);
            }
          }
        }
      }
      if ($errors) {
        // There's no support for setting multiple errors to a form value. We
        // could set (N-1) messages as an error using the messenger service
        // rather than tie them to the 'value_map' element; not sure if that's
        // better.
        $form_state->setErrorByName('value_map', implode(' // ', $errors));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Convert role names to machine names. This is a shortened version of the
    // code in validateForm() that makes assumes about $value_map's structure.
    $value_map = $form_state->getValue('value_map');
    $storable_value_map = [];
    if ($value_map && trim($value_map)) {
      $roles = array_flip($this->getRoleLabels());
      $lines = explode("\n", $value_map);
      foreach ($lines as $line) {
        if (trim($line)) {
          $label = '';
          $parts = explode('|', $line);
          $attribute_parts = [array_shift($parts)];
          while ($parts) {
            $label = trim(implode('|', $parts));
            if (isset($roles[$label])) {
              break;
            }
            $attribute_parts[] = array_shift($parts);
          }
          if (!$parts || !$label) {
            throw new \RuntimeException('Error interpreting %name form value should have been caught by validateForm(); something is wrong with the code.', ['%name' => $value_map]);
          }
          $storable_value_map[] = [
            'attribute_value' => trim(implode('|', $attribute_parts)),
            'role_machine_name' => $roles[$label],
          ];
        }
      }
    }

    $this->configFactory()->getEditable(UserRolesEventSubscriber::CONFIG_OBJECT_NAME)
      ->set('only_first_login', $form_state->getValue('only_first_login'))
      ->set('unassign_roles', array_filter($form_state->getValue('unassign_roles')))
      ->set('default_roles', array_filter($form_state->getValue('default_roles')))
      ->set('saml_attribute', $form_state->getValue('saml_attribute'))
      ->set('saml_attribute_separator', $form_state->getValue('saml_attribute_separator'))
      ->set('value_map', $storable_value_map)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Builds a list of (trimmed) role names keyed by their machine names.
   *
   * @return string[]
   *   The roles keyed by their machine names.
   */
  protected function getRoleLabels() {
    $skip = [
      AccountInterface::ANONYMOUS_ROLE,
      AccountInterface::AUTHENTICATED_ROLE,
    ];
    $roles = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $name => $role) {
      if (!in_array($name, $skip, TRUE)) {
        $roles[$name] = trim($role->label());
      }
    }

    return $roles;
  }

}
