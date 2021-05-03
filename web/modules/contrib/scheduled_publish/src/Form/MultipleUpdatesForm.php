<?php

/**
 * @file
 * Contains \Drupal\scheduled_publish\Form\MultipleUpdatesForm.
 */

namespace Drupal\scheduled_publish\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;

class MultipleUpdatesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_publish_multiple_updates_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Handle ajax form submissions.
    $this->handleSubmissions($form_state);

    $wrapper = 'scheduled-publish-form-wrapper';
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper . '">';
    $form['#suffix'] = '</div>';

    // CONTENT MODERATION WORKFLOWS
    $workflow_objects = $form_state->get(['scheduled_publish', 'workflow_objects']);
    if (!$workflow_objects) {
      $workflow_objects = Workflow::loadMultipleByType('content_moderation');
      $form_state->set(['scheduled_publish', 'workflow_objects'], $workflow_objects);
    }
    if (!$workflow_objects) {
      $form['message'] = [
        '#theme_wrappers' => ['container'],
        '#markup' => $this->t('No content moderation workflows found.'),
      ];
      return $form;
    }

    // Warn users about how this works.
    $form['warning'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('Upon saving all existing scheduled publishing entries for the selected entities will be removed and new ones added.<br>The first entity is used to determine valid state changes. Invalid options for any following entities will not be saved.'),
    ];

    $workflows = [];
    foreach ($workflow_objects as $value) {
      $workflows[$value->get('id')] = $value->get('label');
    }
    $workflow = $form_state->get(['scheduled_publish', 'workflow']);
    if (!$workflow) {
      $workflow = key($workflows);
      $form_state->set(['scheduled_publish', 'workflow'], $workflow);
    }
    $valid_entity_bundles = $workflow_objects[$workflow]->get('type_settings');
    $valid_entity_bundles = $valid_entity_bundles['entity_types']['node'] ?? [];

    // Allow workflow selection if there's more than one.
    if (count($workflows) > 1) {
      $form['workflow'] = [
        '#type' => 'select',
        '#title' => $this->t('Workflow'),
        '#options' => $workflows,
        '#description' => $this->t('Entities will be limited to the selected workflow.'),
        '#name' => 'workflow',
        '#limit_validation_errors' => [['workflow']],
        '#default_value' => $workflow,
        '#ajax' => [
          'callback' => [get_called_class(), 'getForm'],
          'wrapper' => $wrapper,
        ],
      ];
    }

    // ENTITIES
    $form['entities'] = [
      '#type' => 'fieldset',
      '#title' => t('Content'),
    ];
    $entities = $form_state->get(['scheduled_publish', 'entities']);
    $entities_count = 0;
    if (!isset($entities)) {
      $entities = [];
      $form_state->set(['scheduled_publish', 'entities'], $entities);
    }
    elseif ($entities_count = count($entities)) {
      foreach ($entities as $key => $value) {
        $form['entities']['entries'][$key] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'node',
          '#selection_settings' => [
            'target_bundles' => $valid_entity_bundles,
          ],
          '#default_value' => $value,
        ];
      }
    }

    $form['entities']['entries'][$entities_count] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => $valid_entity_bundles,
      ],
    ];

    $form['entities']['save'] = [
      '#type' => 'button',
      '#value' => $this->t('Select content'),
      '#name' => 'sp-entities-save',
      '#limit_validation_errors' => [['entities']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
    ];

    // STATES
    $form['states'] = [
      '#type' => 'fieldset',
      '#title' => t('States'),
      '#element_validate' => [[get_class($this), 'validateElement']],
    ];
    // At least one entity is needed.
    if (!$entities) {
      $form['states']['message'] = [
        '#theme_wrappers' => ['container'],
        '#markup' => $this->t('Add at least one entity to be able to add status updates.'),
      ];
      return $form;
    }

    $states = $form_state->get(['scheduled_publish', 'states']);
    if (!isset($states)) {
      $states = [];
      $form_state->set(['scheduled_publish', 'states'], $states);
    }
    elseif (count($states)) {
      $this->addStates($form['states'], $form_state, $wrapper);
    }

    // Use the first entity to get moderation options.
    $entity = $form_state->get(['scheduled_publish', 'first_entity']);
    if (!$entity) {
      $entity = \Drupal::entityTypeManager()->getStorage('node')->load(reset($entities));
      $form_state->set(['scheduled_publish', 'first_entity'], $entity);
    }
    $last_state = end($states);
    if ($last_state) {
      $orig_status = $entity->moderation_state->value;
      $entity->moderation_state->value = $last_state['state'];
    }
    $m_options = $this->getModerationOptions($entity);
    if ($last_state) {
      $entity->moderation_state->value = $orig_status;
    }
    $form['states']['moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderation state change'),
      '#options' => $m_options,
    ];

    $form['states']['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Scheduled date'),
      '#description' => $this->t('The datetime of the scheduled publish'),
      '#date_increment' => 1,
      '#date_timezone' => date_default_timezone_get(),
    ];

    $form['states']['add'] = [
      '#type' => 'button',
      '#value' => $this->t('Add status update'),
      '#name' => 'sp-state-add',
      '#limit_validation_errors' => [['states']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 10,
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      // Already validated data from form_state is saved.
      '#limit_validation_errors' => [],
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Get moderation options.
   */
  protected function getModerationOptions($entity) {
    $states = [];
    /** @var \Drupal\content_moderation\ModerationInformation $moderationInformationService */
    $moderationInformationService = \Drupal::service('content_moderation.moderation_information');

    if ($moderationInformationService->isModeratedEntity($entity)) {
      /** @var \Drupal\content_moderation\StateTransitionValidation $transitionValidationService */
      $transitionValidationService = \Drupal::service('content_moderation.state_transition_validation');
      $transitions = $transitionValidationService->getValidTransitions($entity, \Drupal::currentUser());
      foreach ($transitions as $key => $value) {
        $states[$transitions[$key]->to()->id()] = $transitions[$key]->label();
      }
    }

    return $states;
  }

  /**
   * Adds existing state entries to the form.
   */
  protected function addStates(array &$form, FormStateInterface $form_state, $wrapper) {
    $states = $form_state->get(['scheduled_publish', 'states']);
    $form['entries'] = [
      '#tree' => TRUE,
      '#type' => 'table',
    ];
    foreach ($states as $key => $value) {
      // First check to see if this state should be displayed as a form.
      if (!empty($value['form'])) {
        // Add the appropriate form.
        $form['entries'][$key]['form'] = [
          '#type' => 'container',
          '#parents' => ['states', 'entries', $key, 'form'],
          '#wrapper_attributes' => ['colspan' => 4],
        ];
        if ($value['form'] == 'edit') {
          $this->buildStateEditForm($form['entries'][$key]['form'], $wrapper, $value);
        }
        elseif ($value['form'] == 'remove') {
          $this->buildStateRemoveForm($form['entries'][$key]['form'], $wrapper, $value);
        }
      }
      else {
        $row = &$form['entries'][$key];
        $row['date'] = [
          '#markup' => $value['date_display'],
        ];
        $row['state'] = [
          '#markup' => $value['state_display'],
        ];
        $row['edit'] = [
          '#type' => 'button',
          '#value' => $this->t('Edit'),
          '#name' => 'sp-state-edit-' . $key,
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [get_called_class(), 'getForm'],
            'wrapper' => $wrapper,
          ],
          '#sp_row_delta' => $key,
        ];
        $row['remove'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'sp-state-remove-' . $key,
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [get_called_class(), 'getForm'],
            'wrapper' => $wrapper,
          ],
          '#sp_row_delta' => $key,
        ];
      }
    }
  }

  /**
   * Builds the state entry edit form.
   */
  protected function buildStateEditForm(&$form, $wrapper, $entry) {
    $form['#element_validate'] = [[get_class($this), 'validateElement']];

    $form['moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderation state change'),
      '#default_value' => $entry['state'],
      '#options' => $entry['state_options'],
    ];

    $form['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Scheduled date'),
      '#default_value' => new DrupalDateTime($entry['date'], ScheduledPublish::STORAGE_TIMEZONE),
      '#date_increment' => 1,
      '#date_timezone' => date_default_timezone_get(),
      '#element_validate' => [],
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 10,
    ];
    $form['actions']['sp_edit_confirm'] = [
      '#type' => 'button',
      '#value' => $this->t('Save'),
      '#name' => 'sp-state-edit-confirm-' . $entry['delta'],
      '#limit_validation_errors' => [$form['#parents']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
      '#sp_row_delta' => $entry['delta'],
    ];
    $form['actions']['sp_edit_cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#name' => 'sp-state-edit-cancel-' . $entry['delta'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
      '#sp_row_delta' => $entry['delta'],
    ];
  }

  /**
   * Builds the state entry remove form.
   */
  protected function buildStateRemoveForm(&$form, $wrapper, $entry) {
    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('Are you sure you want to remove "%shceduling_option"?',
        ['%shceduling_option' => $entry['date_display'] . ' ' . $entry['state_display']]),
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 10,
    ];
    $form['actions']['sp_remove_confirm'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove'),
      '#name' => 'sp-state-remove-confirm-' . $entry['delta'],
      '#limit_validation_errors' => [$form['#parents']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
      '#sp_row_delta' => $entry['delta'],
    ];
    $form['actions']['sp_remove_cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#name' => 'sp-state-remove-cancel-' . $entry['delta'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'getForm'],
        'wrapper' => $wrapper,
      ],
      '#sp_row_delta' => $entry['delta'],
    ];
  }

  /**
   * Returns the form.
   */
  public static function getForm($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Handle element validation.
   */
  public static function validateElement($element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || !isset($triggering_element['#name'])) {
      return;
    }

    $error_past_date = t("Date cannot be in the past");

    // Saving new state entries.
    if ($triggering_element['#name'] === 'sp-state-add') {
      $values = $form_state->getValue('states');
      if (!$values || !$values['value']) {
        $form_state->setError($element, t('All fields need to be filled'));
      }
      elseif ($values['value'] < new DrupalDateTime()) {
        $form_state->setError($element, $error_past_date);
      }
    }

    if (!isset($triggering_element['#sp_row_delta'])) {
      return;
    }

    // Editing state entries.
    $button_name = 'sp-state-edit-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $value_array = [
        'states',
        'entries',
        $triggering_element['#sp_row_delta'],
        'form',
      ];
      $values = $form_state->getValue($value_array);
      if (!$values['moderation_state'] || !$values['value']['date'] || !$values['value']['time']) {
        $form_state->setError($element, t('All fields need to be filled'));
      }
      elseif (strtotime($values['value']['date'] . ' ' . $values['value']['time']) < time()) {
        $form_state->setError($element, $error_past_date);
      }
    }
  }

  /**
   * Handles submissions.
   */
  public function handleSubmissions(FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || !isset($triggering_element['#name'])) {
      return;
    }

    // Selecting workflow.
    if ($triggering_element['#name'] === 'workflow') {
      $workflow = $form_state->getValue('workflow');
      $form_state->set(['scheduled_publish', 'workflow'], $workflow);
      return;
    }

    // Saving new entities.
    if ($triggering_element['#name'] === 'sp-entities-save') {
      $this->submitSaveEntities($form_state);
      return;
    }

    // Saving new states.
    if ($triggering_element['#name'] === 'sp-state-add') {
      $this->submitSaveState($form_state);
    }

    if (!isset($triggering_element['#sp_row_delta'])) {
      return;
    }

    $states = $form_state->get(['scheduled_publish', 'states']);

    // Enabling remove form.
    $button_name = 'sp-state-remove-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $states[$triggering_element['#sp_row_delta']]['form'] = 'remove';
    }

    // Canceling remove form.
    $button_name = 'sp-state-remove-cancel-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $states[$triggering_element['#sp_row_delta']]['form'] = NULL;
    }

    // Saving remove form.
    $button_name = 'sp-state-remove-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      unset($states[$triggering_element['#sp_row_delta']]);
    }

    // Enabling edit form.
    $button_name = 'sp-state-edit-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $states[$triggering_element['#sp_row_delta']]['form'] = 'edit';
    }

    // Canceling edit form.
    $button_name = 'sp-state-edit-cancel-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $states[$triggering_element['#sp_row_delta']]['form'] = NULL;
    }

    // Saving edit form.
    $button_name = 'sp-state-edit-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $value_array = [
        'states',
        'entries',
        $triggering_element['#sp_row_delta'],
        'form',
      ];
      $values = $form_state->getValue($value_array);
      if ($values['value']['date'] && $values['value']['time']) {
        $date = new DrupalDateTime($values['value']['date'] . ' ' . $values['value']['time'], date_default_timezone_get());
        $date_display = $date->format('d.m.Y - H:i');
        $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
        $states[$triggering_element['#sp_row_delta']] = [
          'date' => $date->format(ScheduledPublish::DATETIME_STORAGE_FORMAT),
          'date_display' => $date_display,
          'state' => $values['moderation_state'],
          'state_display' => NULL,
          'state_options' => [],
          'delta' => NULL,
          'form' => NULL,
        ];
      }
    }

    $form_state->set(['scheduled_publish', 'states'], $states);
    $this->handleStates($form_state);
  }

  /**
   * Saves entities into form state.
   */
  public function submitSaveEntities(FormStateInterface $form_state) {
    $values = $form_state->getValue(['entities', 'entries']);
    if ($values) {
      $entities = [];
      foreach ($values as $value) {
        if ($value) {
          $entities[] = $value;
        }
      }
      $form_state->set(['scheduled_publish', 'entities'], $entities);
      // Update first entity.
      $entity = \Drupal::entityTypeManager()->getStorage('node')->load(reset($entities));
      $form_state->set(['scheduled_publish', 'first_entity'], $entity);
    }
  }

  /**
   * Saves state into form state.
   */
  public function submitSaveState(FormStateInterface $form_state) {
    $values = $form_state->getValue('states');
    if ($values && isset($values['moderation_state'], $values['value'])) {
      $states = $form_state->get(['scheduled_publish', 'states']);
      $date = $values['value'];
      $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      $date_display = $date->format('d.m.Y - H:i');
      $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
      $states[] = [
        'date' => $date->format(ScheduledPublish::DATETIME_STORAGE_FORMAT),
        'date_display' => $date_display,
        'state' => $values['moderation_state'],
        'state_display' => NULL,
        'state_options' => [],
        'delta' => NULL,
        'form' => NULL,
      ];
      $form_state->set(['scheduled_publish', 'states'], $states);
      $this->handleStates($form_state);
      // Reset form values.
      $input = $form_state->getUserInput();
      $input['states'] = [];
      $form_state->setUserInput($input);
    }
  }

  /**
   * Handles state values, clean-up and ordering.
   */
  public function handleStates(FormStateInterface $form_state) {
    $entity = $form_state->get(['scheduled_publish', 'first_entity']);
    $orig_status = $entity->moderation_state->value;
    $m_options = $this->getModerationOptions($entity);

    $states = $form_state->get(['scheduled_publish', 'states']);
    // Make sure states are ordered correctly.
    $this->handleStateOrdering($states);

    foreach ($states as $key => $state) {
      if (isset($m_options[$state['state']])) {
        $states[$key]['state_display'] = $m_options[$state['state']];
        $states[$key]['state_options'] = $m_options;
        $entity->moderation_state->value = $state['state'];
        $m_options = $this->getModerationOptions($entity);
      }
      else {
        // Delete invalid state changes.
        unset($states[$key]);
      }
    }

    $entity->moderation_state->value = $orig_status;
    // Adjust ordering in case any invalid entries got removed.
    $this->handleStateOrdering($states);
    $form_state->set(['scheduled_publish', 'states'], $states);
  }

  /**
   * Re-orders states and adds/changes their delta values based on date.
   */
  public static function handleStateOrdering(&$states) {
    usort($states, function($a, $b) {
      $a_timestamp = strtotime($a['date']);
      $b_timestamp = strtotime($b['date']);
      if ($a_timestamp == $b_timestamp) {
        return 0;
      }
      return ($a_timestamp < $b_timestamp) ? -1 : 1;
    });
    // Keys and delta values must always match.
    foreach ($states as $key => $state) {
      $states[$key]['delta'] = $key;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entities = $form_state->get(['scheduled_publish', 'entities']);
    $states = $form_state->get(['scheduled_publish', 'states']);
    if (!$entities || !$states) {
      \Drupal::messenger()->addWarning($this->t('Nothing to update. Add at least one entity and state.'));
      return;
    }

    // Loop over all the saved entities.
    $updated_entities = [];
    foreach ($entities as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('node')->load($entity_id);
      if (!$entity) {
        continue;
      }

      // Get the scheduled field.
      $fields = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      $scheduled_field = FALSE;
      foreach ($fields as $name => $field) {
        if (strpos($name, 'field_') !== FALSE) {
          if ($field->getType() === 'scheduled_publish') {
            $scheduled_field = $name;
            break;
          }
        }
      }
      if (!$scheduled_field) {
        continue;
      }

      // Override scheduling entries with new valid ones.
      $orig_status = $entity->moderation_state->value;
      $m_options = $this->getModerationOptions($entity);
      $valid_states = [];
      foreach ($states as $key => $state) {
        if (isset($m_options[$state['state']])) {
          $valid_states[] = [
            'moderation_state' => $state['state'],
            'value' => $state['date'],
          ];
          $entity->moderation_state->value = $state['state'];
          $m_options = $this->getModerationOptions($entity);
        }
      }
      $entity->moderation_state->value = $orig_status;
      if ($valid_states) {
        $entity->set($scheduled_field, $valid_states);
        $entity->save();
        $updated_entities[] = $entity->label() . ' (' . $entity->id() . ')';
      }
    }

    if (!$updated_entities) {
      \Drupal::messenger()->addWarning($this->t('No entities were updated as there were no valid state changes for any of them.'));
      return;
    }

    $message = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Added scheduled publishing entries for the following entities:'),
      '#items' => $updated_entities,
      '#wrapper_attributes' => ['class' => 'container'],
    ];
    \Drupal::messenger()->addStatus($message);
  }

}
