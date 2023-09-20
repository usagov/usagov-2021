<?php

namespace Drupal\usa_orphaned_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class OrphanedEntitiesSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'orphaned_entities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entityTypeManager = \Drupal::service('entity_type.manager');

    $form['#attached']['library'][] = 'usa_orphaned_entities/orphaned';
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('orphaned_entities.settings');
    $form['entity_reference'] = [];

    $entity_references = $entityTypeManager->getStorage('field_storage_config')->loadByProperties([
      'type' => 'entity_reference',
    ]);

    $form['markup'] = [
      '#type' => 'item',
      '#title' => $this->t('Choose how orphaned entities should be handled by each content type.'),
      '#description' => $this->t('Select for each field, when an entity is deleted, will the entities that reference the deleted entity using that field have their reference field cleared and the entity saved, be deleted, or ignored. Selection refers to what happens to the entity <i>with that field</i>.'),
      '#attributes' => [
        'class' => [
          'markup',
        ],
      ],
    ];

    // Get data and structure it for generating form.
    $reference_field_map = [];
    foreach ($entity_references as $index => $entity_reference) {
      $form['entity_reference'][$index] = $entity_reference;
      $bundles = $entity_reference->getBundles();
      foreach ($bundles as $index => $bundle) {
        $reference_field = $entity_reference->getName();
        $reference_field_map[$bundle][] = $reference_field;
      }
    }

    // Generate form.
    foreach ($reference_field_map as $bundle => $fields) {
      //@todo work out how to get bundle label from here.
      $form[$bundle] = [
        '#type' => 'fieldset',
        '#title' => $bundle,
      ];
      foreach ($fields as $reference_field) {
        $form[$bundle][$bundle . '_' . $reference_field] = [
          '#type' => 'radios',
          '#title' => $reference_field,
          '#options' => [
            'clear' => $this->t('Clear reference'),
            'delete' => $this->t('Delete node'),
            'skip' => $this->t('Do nothing'),
          ],
          '#default_value' => ($config->get('orphaned_entities.' . $bundle . '_' . $reference_field)) ?: 'skip',
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // foreach ($form['entity_reference'] as $index => $entity_reference) {

    //   if (!str_contains($index, '#')) {
    //     $bundles = $entity_reference->getBundles();
    //     foreach ($bundles as $index => $bundle) {
    //       if (empty($form_state->getValue($bundle . '_' . $reference_field)) ||
    //         $form_state->getValue($bundle . '_' . $reference_field) == NULL) {
    //   $reference_field = $entity_reference->getName();
    //         $form_state->setErrorByName($bundle . '_' . $reference_field, $this->t('Field option must be set.'));
    //       }
    //     }
    //   }
    // }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('orphaned_entities.settings');
    foreach ($form['entity_reference'] as $index => $entity_reference) {
      if (!str_contains($index, '#')) {
        $bundles = $entity_reference->getBundles();

        foreach ($bundles as $index => $bundle) {
          $reference_field = $entity_reference->getName();
          $config->set(
              'orphaned_entities.' . $bundle . '_' . $reference_field, $form_state->getValue($bundle . '_' . $reference_field));
        }
      }
    }

    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'orphaned_entities.settings',
    ];
  }

}
