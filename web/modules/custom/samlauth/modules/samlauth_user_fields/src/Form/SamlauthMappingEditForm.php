<?php

namespace Drupal\samlauth_user_fields\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\samlauth\Controller\SamlController;
use Drupal\samlauth_user_fields\EventSubscriber\UserFieldsEventSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding a mapped SAML attribute -> user field.
 */
class SamlauthMappingEditForm extends FormBase {

  /**
   * Field types that can be mapped.
   */
  const MAP_FIELD_TYPES = [
    'address',
    'boolean',
    'email',
    'float',
    'integer',
    'language',
    'link',
    'list_float',
    'list_integer',
    'list_string',
    'string',
    'string_long',
    'telephone',
    'timestamp',
  ];

  /**
   * User fields (of mappable types) that should not be mappable.
   */
  const PREVENT_MAP_FIELDS = [
    // Name and email are mappable, but not from this form.
    'name',
    'mail',
    'uid',
    'status',
    'access',
    'login',
    'init',
    // preferred(_admin)_langcode is mappable. (default_)langcode seem to be
    // standard fields on entities.
    'langcode',
    'default_langcode',
  ];

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * SamlauthMappingEditForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'samlauth_user_fields_edit_form';
  }

  /**
   * Form for adding or editing a mapping.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $mapping_id
   *   (optional) The numeric ID of the mapping.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mapping_id = NULL) {
    $user_fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    $mappings = $this->configFactory()->getEditable(UserFieldsEventSubscriber::CONFIG_OBJECT_NAME)->get('field_mappings');

    // @todo make code that captures all attributes from a SAML authentication
    //   message (only if enabled here via a special temporary option) and
    //   fills a list of possible attribute names. If said list is populated,
    //   we can present a select element in the add/edit screen - though we
    //   always want to keep the option for the user of entering an attribute
    //   name manually, so this will complicate the screen a bit.
    $form['attribute_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SAML Attribute'),
      '#description' => $this->t('The name of the SAML attribute you want to sync to the user profile.'),
      '#required' => TRUE,
      '#default_value' => $mappings[$mapping_id]['attribute_name'] ?? NULL,
    ];

    $options = ['' => $this->t('- Select -')];
    foreach ($user_fields as $name => $field) {
      if (in_array($field->getType(), static::MAP_FIELD_TYPES, TRUE)
          && !in_array($name, static::PREVENT_MAP_FIELDS, TRUE)) {
        $subfields = $this->getSubFields($field);
        $label = $field->getLabel();
        if ($subfields) {
          foreach ($subfields as $sub_name => $sub_label) {
            $options["$name:$sub_name"] = "$label: $sub_label";
          }
        }
        else {
          $options[$name] = $label;
        }
      }
    }
    $field_name = NULL;
    if ($mapping_id !== NULL) {
      $field_name = $mappings[$mapping_id]['field_name'];
      if (!isset($options[$field_name])) {
        $this->messenger()->addError('Currently mapped user field %name is unknown. Saving this form will change the mapping.', ['%name' => $field_name]);
        $field_name = NULL;
      }
    }
    $form['field_name'] = [
      '#type' => 'select',
      '#title' => $this->t('User Field'),
      '#description' => $this->t('The user field you want to sync this attribute to.'),
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $field_name,
    ];

    if ($this->config(SamlController::CONFIG_OBJECT_NAME)->get('map_users')) {
      // The huge description isn't very good UX, but we'll postpone thinking
      // about it until we integrate this mapping with the mapping for
      // name + email - or until someone else sends in a fix for this.
      $form['link_user_order'] = [
        '#type' => 'number',
        '#size' => 2,
        '#title' => $this->t('Link user?'),
        '#description' => $this->t("Provide a value here if a first login should attempt to match an existing non-linked Drupal user on the basis of this field's value. The exact value only matters when multiple link attempts are defined (to determine order of attempts and/or combination with other fields). See the help text with the list for more info.")
        . '<br><em>' . $this->t('Warning: if this attribute can be changed by the IdP user, this has security implications; it enables a user to influence which Drupal user they take over.') . '</em>',
        '#default_value' => $mappings[$mapping_id]['link_user_order'] ?? NULL,
      ];
    }

    // Add this value so we know if it's an add or an edit.
    $form['mapping_id'] = [
      '#type' => 'value',
      '#value' => $mapping_id,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (strpos($form_state->getValue('field_name'), ':') !== FALSE
        && !in_array($form_state->getValue('link_user_order'), ['', NULL], TRUE)) {
      $form_state->setErrorByName('link_user_order', $this->t("Linking by a 'sub field' is not currently supported."));
    }

    // If this is a new mapping, check to make sure a 'same' one isn't already
    // defined.
    $mappings = $this->configFactory()->getEditable(UserFieldsEventSubscriber::CONFIG_OBJECT_NAME)->get('field_mappings');
    if (is_array($mappings)) {
      $our_mapping_id = $form_state->getValue('mapping_id');
      $our_match_id = $form_state->getValue('link_user_order');
      foreach ($mappings as $mapping_id => $mapping) {
        if ($mapping_id != $our_mapping_id || $our_mapping_id === '') {
          if ($our_match_id !== '' && isset($mapping['link_user_order']) && $our_match_id == $mapping['link_user_order']
              && $mapping['field_name'] === $form_state->getValue('field_name')) {
            $form_state->setErrorByName('field_name', $this->t("This user field is already used for the same 'Link' value."));
          }
          // Allow mappings from/to the same attribute/field if both are used in
          // a different match/link expression. It's far fetched, but the
          // duplicate doesn't make a difference for the mapping in practice.
          if (($our_match_id === '' || !isset($mapping['link_user_order']) || $our_match_id == $mapping['link_user_order'])
              && $mapping['field_name'] === $form_state->getValue('field_name')
              && $mapping['attribute_name'] === $form_state->getValue('attribute_name')) {
            $form_state->setErrorByName('field_name', $this->t('This SAML attribute has already been mapped to this field.'));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable(UserFieldsEventSubscriber::CONFIG_OBJECT_NAME);
    $mappings = $config->get('field_mappings');

    $new_mapping = [
      'attribute_name' => $form_state->getValue('attribute_name'),
      'field_name' => $form_state->getValue('field_name'),
      'link_user_order' => $form_state->getValue('link_user_order'),
    ];

    $mapping_id = $form_state->getValue('mapping_id');
    if (!is_array($mappings)) {
      $mappings = [];
    }
    if ($mapping_id !== NULL) {
      $mappings[$mapping_id] = $new_mapping;
    }
    else {
      $mappings[] = $new_mapping;
    }
    $config->set('field_mappings', $mappings)->save();

    $form_state->setRedirect('samlauth_user_fields.list');
  }

  /**
   * Checks if the field has multiple columns that we can map values into.
   *
   * This starts off as a private method with some hardcoded logic because I'm
   * not sure how this will evolve and if it will be general enough.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field to check.
   *
   * @return array
   *   All columns (as keys, with the label as values) inside this field which
   *   can be mapped - or an empty array if the field is a 'simple' field with
   *   just one mappable value column.
   */
  private function getSubFields(FieldDefinitionInterface $field) {
    // Hardcode for address only. It is possible that the below code is general
    // enough for all field types, but I don't know that for sure. I don't want
    // field types that used to be treated as single-value to return an array
    // here, thereby losing compatibility with previous module versions.
    if ($field->getType() !== 'address') {
      return [];
    }

    // A FieldDefinitionInterface does not necessarily have
    // getPropertyDefinitions() and getSchema(). (The basic user fields do,
    // because they extend BaseFieldDefinition which implements both
    // FieldDefinitionInterface and FieldStorageDefinitionInterface.)
    $storage_definition = $field->getFieldStorageDefinition();
    $property_definitions = $storage_definition->getPropertyDefinitions();
    $schema = $storage_definition->getSchema();
    // Not sure which fields we should support; start out with just varchar
    // (which is all fields in case of type 'address'). Also not sure if we
    // want to filter out the address subfields that are set to "invisible"; I
    // guess/hope not.
    $columns = array_filter($schema['columns'], function ($column) {
      return ($column['type'] ?? NULL) === 'varchar';
    });
    $subfields = [];
    if ($columns) {
      foreach (array_keys($columns) as $column_name) {
        if (isset($property_definitions[$column_name])
            && $property_definitions[$column_name] instanceof DataDefinition) {
          $subfields[$column_name] = $property_definitions[$column_name]->getLabel();
        }
        else {
          $subfields[$column_name] = $column_name;
        }
      }
    }

    return $subfields;
  }

}
