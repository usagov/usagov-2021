<?php

/**
 * @file
 * Contains \Drupal\scheduled_publish\Form\DeleteUpdateForm.
 */

namespace Drupal\scheduled_publish\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;

class DeleteUpdateForm extends ConfirmFormBase {

  /**
   * The entity.
   */
  protected $entity;

  /**
   * The field.
   */
  protected $field;

  /**
   * The field delta.
   */
  protected $field_delta;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_publish_delete_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $states = $this->entity->get($this->field)->getValue();
    $prev_state = $states[$this->field_delta - 1] ?? FALSE;
    if ($prev_state) {
      $orig_status = $this->entity->moderation_state->value;
      $this->entity->moderation_state->value = $prev_state['moderation_state'];
    }
    $m_options = $this->getModerationOptions($this->entity);
    if ($prev_state) {
      $this->entity->moderation_state->value = $orig_status;
    }

    $state_display = $states[$this->field_delta]['moderation_state'];
    if (isset($m_options[$states[$this->field_delta]['moderation_state']])) {
      $state_display = $m_options[$states[$this->field_delta]['moderation_state']];
      $state_display .= ' (';
      $state_display .= $states[$this->field_delta]['moderation_state'];
      $state_display .= ')';
    }

    $entity_info = $this->entity->label() . ' (' . $this->entity->id() . ')';
    $date = new DrupalDateTime($states[$this->field_delta]['value'], date_default_timezone_get());
    $date_display = $date->format('d.m.Y - H:i');

    return $this->t('Are you sure you want to delete "@state on @date" status update for the "@node" node?',
      ['@node' => $entity_info, '@state' => $state_display, '@date' => $date_display]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.scheduled_publish.page_1');
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

    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('If this state deletion invalidates any existing transitions those will be deleted as well.'),
    ];

    // Save data into form_state and class variables.
    $form_state->set(['scheduled_publish', 'entity'], $entity);
    $form_state->set(['scheduled_publish', 'field'], $field);
    $form_state->set(['scheduled_publish', 'field_delta'], $field_delta);
    $this->entity = $entity;
    $this->field = $field;
    $this->field_delta = $field_delta;

    return parent::buildForm($form, $form_state);
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
    $entity = $form_state->get(['scheduled_publish', 'entity']);
    $field = $form_state->get(['scheduled_publish', 'field']);
    $delta = $form_state->get(['scheduled_publish', 'field_delta']);
    $states = $entity->get($field)->getValue();

    unset($states[$delta]);
    $this->handleStates($form_state, $states);

    // Reload entity to be sure it's not old.
    $entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
    $entity->set($field, $states);
    $entity->save();

    $this->messenger()->addStatus($this->t('Status update deleted.'));
  }

}
