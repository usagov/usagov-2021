<?php

/**
 * @file
 * Contains \Drupal\scheduled_publish\Form\EditUpdateForm.
 */

namespace Drupal\scheduled_publish\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;

class EditUpdateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_publish_edit_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity = NULL, $field_delta = NULL) {
    if (!isset($entity) || !isset($field_delta)) {
      $form['message'] = [
        '#theme_wrappers' => ['container'],
        '#markup' => $this->t('A valid entity and field delta must be provided.'),
      ];
      return $form;
    }

    $fields = $this->getScheduledFields($entity->getEntityTypeId(), $entity->bundle());
    $field = reset($fields);
    $states = $entity->get($field)->getValue();
    if (!isset($states[$field_delta])) {
      $form['message'] = [
        '#theme_wrappers' => ['container'],
        '#markup' => $this->t('This status update does not exist.'),
      ];
      return $form;
    }

    // Save data into form_state.
    $form_state->set(['scheduled_publish', 'entity'], $entity);
    $form_state->set(['scheduled_publish', 'field'], $field);
    $form_state->set(['scheduled_publish', 'field_delta'], $field_delta);

    $entity_info = $entity->label() . ' (' . $entity->id() . ')';
    $form['#title'] = $this->t('Edit status update for the "@node" node', ['@node' => $entity_info]);
    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('If this state change invalidates any existing transitions those will be deleted.'),
    ];

    $prev_state = $states[$field_delta - 1] ?? FALSE;
    if ($prev_state) {
      $orig_status = $entity->moderation_state->value;
      $entity->moderation_state->value = $prev_state['moderation_state'];
    }
    $m_options = $this->getModerationOptions($entity);
    if ($prev_state) {
      $entity->moderation_state->value = $orig_status;
    }

    $form['moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderation state change'),
      '#options' => $m_options,
      '#default_value' => isset($m_options[$states[$field_delta]['moderation_state']]) ?
        $states[$field_delta]['moderation_state'] : NULL,
      '#required' => TRUE,
    ];

    $form['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Scheduled date'),
      '#description' => $this->t('Date & time of the scheduled state change'),
      '#date_increment' => 1,
      '#date_timezone' => date_default_timezone_get(),
      '#default_value' => new DrupalDateTime($states[$field_delta]['value'], ScheduledPublish::STORAGE_TIMEZONE),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 10,
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $query = \Drupal::service('request_stack')->getCurrentRequest()->query;
    $url = Url::fromRoute('view.scheduled_publish.page_1');
    if ($query->has('destination')) {
      $options = UrlHelper::parse($query->get('destination'));
      try {
        $url = Url::fromUserInput('/' . ltrim($options['path'], '/'), $options);
      }
      catch (\InvalidArgumentException $e) {
        // Suppress the exception.
      }
    }
    if ($url) {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#attributes' => ['class' => ['button']],
        '#url' => $url,
      ];
    }

    return $form;
  }

  /**
   * Returns scheduled publish fields
   *
   * @param string $entityTypeName
   * @param string $bundleName
   *
   * @return array
   */
  protected function getScheduledFields(string $entityTypeName, string $bundleName): array {
    $scheduledFields = [];
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entityTypeName, $bundleName);
    foreach ($fields as $fieldName => $field) {
      /** @var FieldConfig $field */
      if (strpos($fieldName, 'field_') !== FALSE) {
        if ($field->getType() === 'scheduled_publish') {
          $scheduledFields[] = $fieldName;
        }
      }
    }

    return $scheduledFields;
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
   * Handles state values, clean-up and ordering.
   */
  public function handleStates(FormStateInterface $form_state, &$states) {
    $entity = $form_state->get(['scheduled_publish', 'entity']);
    $orig_status = $entity->moderation_state->value;
    $m_options = $this->getModerationOptions($entity);

    // Make sure states are ordered correctly.
    $this->handleStateOrdering($states);

    foreach ($states as $key => $state) {
      if (isset($m_options[$state['moderation_state']])) {
        $entity->moderation_state->value = $state['moderation_state'];
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
  }

  /**
   * Re-orders states and adds/changes their delta values based on date.
   */
  public static function handleStateOrdering(&$states) {
    usort($states, function($a, $b) {
      $a_timestamp = strtotime($a['value']);
      $b_timestamp = strtotime($b['value']);
      if ($a_timestamp == $b_timestamp) {
        return 0;
      }
      return ($a_timestamp < $b_timestamp) ? -1 : 1;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $state = $form_state->getValue('moderation_state');
    $date = $form_state->getValue('value');
    $date->setTimezone(new \DateTimezone(ScheduledPublish::STORAGE_TIMEZONE));
    $value = $date->format(ScheduledPublish::DATETIME_STORAGE_FORMAT);

    $entity = $form_state->get(['scheduled_publish', 'entity']);
    $field = $form_state->get(['scheduled_publish', 'field']);
    $delta = $form_state->get(['scheduled_publish', 'field_delta']);
    $states = $entity->get($field)->getValue();

    $states[$delta]['moderation_state'] = $state;
    $states[$delta]['value'] = $value;
    $this->handleStates($form_state, $states);

    // Reload entity to be sure it's not old.
    $entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
    $entity->set($field, $states);
    $entity->save();

    $this->messenger()->addStatus($this->t('Status update changed.'));
  }

}
