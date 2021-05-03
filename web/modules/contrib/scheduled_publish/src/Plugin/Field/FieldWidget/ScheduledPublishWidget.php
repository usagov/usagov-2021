<?php

namespace Drupal\scheduled_publish\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;

/**
 * Plugin implementation of the 'scheduled_publish_widget' widget.
 *
 * @FieldWidget(
 *   id = "scheduled_publish",
 *   label = @Translation("Scheduled Publish"),
 *   field_types = {
 *     "scheduled_publish"
 *   },
 *   multiple_values = TRUE
 * )
 */
class ScheduledPublishWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['#attached']['library'][] = 'scheduled_publish/scheduled_publish_widget';

    // No support for adding default values.
    if ($form_state->getBuildInfo()['base_form_id'] === 'field_config_form') {
      return $element;
    }
    $formObject = $form_state->getFormObject();
    $entity = $formObject->getEntity();
    // In case the entity cannot be moderated.
    if (!isset($entity->moderation_state)) {
      return $element;
    }

    $field_name = $this->fieldDefinition->getName();

    // Handle widget form submissions via various buttons.
    $this->handleSubmissions($form_state);

    // Build a parents array for this element's values in the form.
    $parents = array_merge($element['#field_parents'], [
      $field_name,
    ]);

    $workflow = \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($entity);
    $current_state = $workflow->getTypePlugin()->getState($entity->moderation_state->value);

    $wrapper = 'scheduled-publish-form-' . $field_name;
    $element = [
      '#type' => 'details',
      '#title' => t('Scheduled moderation state change'),
      '#description' => t('Schedule a change from the current %state state.', ['%state' => $current_state->label()]),
      '#weight' => -5,
      '#group' => 'advanced',
      '#open' => FALSE,
      '#tree' => TRUE,
      '#sp_root' => TRUE,
      '#prefix' => '<div id="' . $wrapper . '" class="scheduled-publish-wrapper">',
      '#suffix' => '</div>',
      '#element_validate' => [[get_class($this), 'validateElement']],
    ] + $element;

    $this->prepareFormState($form_state, $items, $field_name);

    $entries = $form_state->get(['scheduled_publish', $field_name]);
    $entries_count = count($entries);
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $cardinality_reached = ($cardinality > 0 && $entries_count == $cardinality);

    if ($entries_count) {
      $element['#open'] = TRUE;
      $this->addEntries($element, $form_state, $parents, $field_name, $wrapper);
    }

    // Do not return the rest of the form if cardinality count has been reached.
    if ($cardinality_reached) {
      return $element;
    }

    $last_entry = end($entries);
    if ($last_entry) {
      $orig_status = $entity->moderation_state->value;
      $entity->moderation_state->value = $last_entry['state'];
    }
    $m_options = $this->getModerationOptions($entity);
    if ($last_entry) {
      $entity->moderation_state->value = $orig_status;
    }
    $element['moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderation state change'),
      '#options' => $m_options,
    ];

    $element['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Scheduled date & time'),
      '#description' => $this->t('Future date & time for the moderation state change.'),
      '#date_increment' => 1,
      '#date_timezone' => date_default_timezone_get(),
    ];

    $element['add'] = [
      '#type' => 'button',
      '#value' => $this->t('Schedule state change'),
      '#name' => 'sp-' . $field_name . '-entry-add',
      '#limit_validation_errors' => [$parents],
      '#sp_id' => $field_name,
      '#ajax' => [
        'callback' => [get_called_class(), 'getElement'],
        'wrapper' => $wrapper,
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Make sure values stored in form_state get saved into database.
    $field_name = $this->fieldDefinition->getName();
    $entries = $form_state->get(['scheduled_publish', $field_name]);

    $values = [];
    foreach ($entries as $key => $entry) {
      $values[$key] = [
        'moderation_state' => $entry['state'],
        'value' => $entry['date'],
      ];
    }

    return $values;
  }

  /**
   * Prepares the form state for the current widget.
   */
  protected function prepareFormState(FormStateInterface $form_state, FieldItemListInterface $items, $field_name) {
    $entries = $form_state->get(['scheduled_publish', $field_name]);
    if (!isset($entries)) {
      $entries = [];
      // Store the $items in the form state for further manipulation.
      foreach ($items as $delta => $item) {
        $date = $item->date;
        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $date_display = $date->format('d.m.Y - H:i');
        $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
        $entries[$delta] = [
          'date' => $date->format(ScheduledPublish::DATETIME_STORAGE_FORMAT),
          'date_display' => $date_display,
          'state' => $item->moderation_state,
          'state_display' => NULL,
          'state_options' => [],
          'delta' => $delta,
          'form' => NULL,
        ];
      }
      $form_state->set(['scheduled_publish', $field_name], $entries);
      $this->handleEntries($form_state, $field_name);
    }
  }

  /**
   * Adds existing entries to the widget.
   */
  protected function addEntries(&$element, FormStateInterface $form_state, $parents, $field_name, $wrapper) {
    $entries = $form_state->get(['scheduled_publish', $field_name]);
    $element['entries'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'scheduled-publish-entries'],
    ];
    foreach ($entries as $key => $value) {
      // First check to see if this entry should be displayed as a form.
      if (!empty($value['form'])) {
        // Add the appropriate form.
        $element['entries'][$key]['form'] = [
          '#type' => 'container',
          '#attributes' => ['class' => 'scheduled-publish-entry-form'],
          '#parents' => array_merge($parents, ['entries', $key, 'form']),
          '#wrapper_attributes' => ['colspan' => 4],
        ];
        if ($value['form'] == 'edit') {
          $this->buildEditForm($element['entries'][$key]['form'], $wrapper, $field_name, $value, $form_state);
        }
        elseif ($value['form'] == 'remove') {
          $this->buildRemoveForm($element['entries'][$key]['form'], $wrapper, $field_name, $value);
        }
      }
      else {
        $element['entries'][$key] = [
          '#type' => 'container',
          '#attributes' => ['class' => 'scheduled-publish-entry'],
          'date' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $value['date_display'],
          ],
          'state' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $value['state_display'],
            '#attributes' => ['class' => 'scheduled-publish-entry-state'],
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => 'scheduled-publish-entry-actions'],
            'edit' => [
              '#type' => 'button',
              '#value' => $this->t('Edit'),
              '#name' => 'sp-' . $field_name . '-entry-edit-' . $key,
              '#limit_validation_errors' => [],
              '#ajax' => [
                'callback' => [get_called_class(), 'getElement'],
                'wrapper' => $wrapper,
              ],
              '#sp_id' => $field_name,
              '#sp_row_delta' => $key,
            ],
            'remove' => [
              '#type' => 'button',
              '#value' => $this->t('Remove'),
              '#name' => 'sp-' . $field_name . '-entry-remove-' . $key,
              '#limit_validation_errors' => [],
              '#ajax' => [
                'callback' => [get_called_class(), 'getElement'],
                'wrapper' => $wrapper,
              ],
              '#sp_id' => $field_name,
              '#sp_row_delta' => $key,
            ],
          ],
        ];
      }
    }
  }

  /**
   * Builds the entry edit form.
   */
  protected function buildEditForm(&$form, $wrapper, $field_name, $entry, FormStateInterface $form_state) {
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
      '#name' => 'sp-' . $field_name . '-edit-confirm-' . $entry['delta'],
      '#limit_validation_errors' => [$form['#parents']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getElement'],
        'wrapper' => $wrapper,
      ],
      '#sp_id' => $field_name,
      '#sp_row_delta' => $entry['delta'],
    ];
    $form['actions']['sp_edit_cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#name' => 'sp-' . $field_name . '-edit-cancel-' . $entry['delta'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'getElement'],
        'wrapper' => $wrapper,
      ],
      '#sp_id' => $field_name,
      '#sp_row_delta' => $entry['delta'],
    ];
  }

  /**
   * Builds the entry remove form.
   */
  protected function buildRemoveForm(&$form, $wrapper, $field_name, $entry) {
    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('Are you sure you want to remove "%scheduling_option"?',
        ['%scheduling_option' => $entry['date_display'] . ' ' . $entry['state_display']]),
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 10,
    ];
    $form['actions']['sp_remove_confirm'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove'),
      '#name' => 'sp-' . $field_name . '-remove-confirm-' . $entry['delta'],
      '#limit_validation_errors' => [$form['#parents']],
      '#ajax' => [
        'callback' => [get_called_class(), 'getElement'],
        'wrapper' => $wrapper,
      ],
      '#sp_id' => $field_name,
      '#sp_row_delta' => $entry['delta'],
    ];
    $form['actions']['sp_remove_cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#name' => 'sp-' . $field_name . '-remove-cancel-' . $entry['delta'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'getElement'],
        'wrapper' => $wrapper,
      ],
      '#sp_id' => $field_name,
      '#sp_row_delta' => $entry['delta'],
    ];
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
   * Returns a SP widget nearest to the triggering element.
   */
  public static function getElement($form, FormStateInterface $form_state) {
    $element = [];
    $triggering_element = $form_state->getTriggeringElement();

    // Remove the action.
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);

    while (!isset($element['#sp_root'])) {
      $element = NestedArray::getValue($form, $array_parents);
      array_pop($array_parents);
    }

    return $element;
  }

  /**
   * Handle element validation.
   */
  public static function validateElement($element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || !isset($triggering_element['#sp_id'], $triggering_element['#name'])) {
      return;
    }

    $error_past_date = t("Date cannot be in the past");

    // Saving new entries.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-entry-add';
    if ($triggering_element['#name'] === $button_name) {
      $values = $form_state->getValue($triggering_element['#sp_id']);
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

    // Editing entries.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-edit-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $value_array = [
        $triggering_element['#sp_id'],
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
    if (!$triggering_element || !isset($triggering_element['#sp_id'], $triggering_element['#name'])) {
      return;
    }

    // Saving new entries.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-entry-add';
    if ($triggering_element['#name'] === $button_name) {
      $this->submitSaveEntry($form_state, $triggering_element);
    }

    if (!isset($triggering_element['#sp_row_delta'])) {
      return;
    }

    $entries = $form_state->get(['scheduled_publish', $triggering_element['#sp_id']]);

    // Enabling remove form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-entry-remove-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $entries[$triggering_element['#sp_row_delta']]['form'] = 'remove';
    }

    // Canceling remove form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-remove-cancel-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $entries[$triggering_element['#sp_row_delta']]['form'] = NULL;
    }

    // Saving remove form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-remove-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      unset($entries[$triggering_element['#sp_row_delta']]);
    }

    // Enabling edit form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-entry-edit-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $entries[$triggering_element['#sp_row_delta']]['form'] = 'edit';
    }

    // Canceling edit form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-edit-cancel-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $entries[$triggering_element['#sp_row_delta']]['form'] = NULL;
    }

    // Saving edit form.
    $button_name = 'sp-' . $triggering_element['#sp_id'] . '-edit-confirm-' . $triggering_element['#sp_row_delta'];
    if ($triggering_element['#name'] === $button_name) {
      $value_array = [
        $triggering_element['#sp_id'],
        'entries',
        $triggering_element['#sp_row_delta'],
        'form',
      ];
      $values = $form_state->getValue($value_array);
      if ($values['value']['date'] && $values['value']['time']) {
        $date = new DrupalDateTime($values['value']['date'] . ' ' . $values['value']['time'], date_default_timezone_get());
        $date_display = $date->format('d.m.Y - H:i');
        $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
        $entries[$triggering_element['#sp_row_delta']] = [
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

    $form_state->set(['scheduled_publish', $triggering_element['#sp_id']], $entries);
    $this->handleEntries($form_state, $triggering_element['#sp_id']);
  }

  /**
   * Saves entry into form state.
   */
  public function submitSaveEntry(FormStateInterface $form_state, $triggering_element) {
    $values = $form_state->getValue($triggering_element['#sp_id']);
    if ($values && isset($values['moderation_state'], $values['value'])) {
      $entries = $form_state->get(['scheduled_publish', $triggering_element['#sp_id']]);
      $date = $values['value'];
      $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      $date_display = $date->format('d.m.Y - H:i');
      $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
      $entries[] = [
        'date' => $date->format(ScheduledPublish::DATETIME_STORAGE_FORMAT),
        'date_display' => $date_display,
        'state' => $values['moderation_state'],
        'state_display' => NULL,
        'state_options' => [],
        'delta' => NULL,
        'form' => NULL,
      ];
      $form_state->set(['scheduled_publish', $triggering_element['#sp_id']], $entries);
      $this->handleEntries($form_state, $triggering_element['#sp_id']);
      // Reset form values.
      $input = $form_state->getUserInput();
      $input[$triggering_element['#sp_id']] = [];
      $form_state->setUserInput($input);
    }
  }

  /**
   * Handles entry values, clean-up and ordering.
   */
  public function handleEntries(FormStateInterface $form_state, $field_name) {
    $formObject = $form_state->getFormObject();
    $entity = $formObject->getEntity();
    $orig_status = $entity->moderation_state->value;
    $m_options = $this->getModerationOptions($entity);

    $entries = $form_state->get(['scheduled_publish', $field_name]);
    // Make sure entries are ordered correctly.
    $this->handleEntryOrdering($entries);

    foreach ($entries as $key => $entry) {
      if (isset($m_options[$entry['state']])) {
        $entries[$key]['state_display'] = $m_options[$entry['state']];
        $entries[$key]['state_options'] = $m_options;
        $entity->moderation_state->value = $entry['state'];
        $m_options = $this->getModerationOptions($entity);
      }
      else {
        // Delete invalid state changes.
        unset($entries[$key]);
      }
    }

    $entity->moderation_state->value = $orig_status;
    // Adjust ordering in case any invalid entries got removed.
    $this->handleEntryOrdering($entries);
    $form_state->set(['scheduled_publish', $field_name], $entries);
  }

  /**
   * Re-orders entries and adds/changes their delta values based on date.
   */
  public static function handleEntryOrdering(&$entries) {
    usort($entries, function($a, $b) {
      $a_timestamp = strtotime($a['date']);
      $b_timestamp = strtotime($b['date']);
      if ($a_timestamp == $b_timestamp) {
        return 0;
      }
      return ($a_timestamp < $b_timestamp) ? -1 : 1;
    });
    // Keys and delta values must always match.
    foreach ($entries as $key => $entry) {
      $entries[$key]['delta'] = $key;
    }
  }

}
