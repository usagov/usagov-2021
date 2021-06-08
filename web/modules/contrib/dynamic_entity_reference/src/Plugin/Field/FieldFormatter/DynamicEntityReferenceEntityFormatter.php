<?php

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;

/**
 * Plugin implementation of the 'rendered entity' formatter.
 *
 * @FieldFormatter(
 *   id = "dynamic_entity_reference_entity_view",
 *   label = @Translation("Rendered entity"),
 *   description = @Translation("Display the referenced entities rendered by entity_view()."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 */
class DynamicEntityReferenceEntityFormatter extends EntityReferenceEntityFormatter {

  use DynamicEntityReferenceFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = array_keys($labels[(string) t('Content', [], ['context' => 'Entity type group'])]);
    return array_fill_keys($options, [
      'view_mode' => 'default',
      'link' => FALSE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = $labels[(string) t('Content', [], ['context' => 'Entity type group'])];
    $entity_type_ids = DynamicEntityReferenceItem::getTargetTypes($this->getFieldSettings());
    $available = [];
    foreach ($this->getSettings() as $key => $value) {
      if (in_array($key, array_values($entity_type_ids))) {
        $available[$key] = $value;
      }
    }
    if (!empty($available)) {
      return array_map(function ($entity_type_id, $settings) use ($options) {
        $view_mode = $this->entityDisplayRepository->getViewModeOptions($entity_type_id);
        return t('@entity view mode: @mode', [
          '@entity' => $options[$entity_type_id],
          '@mode' => $view_mode[$settings['view_mode']],
        ]);
      }, array_keys($available), $available);
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = $labels[(string) t('Content', [], ['context' => 'Entity type group'])];
    $entity_type_ids = DynamicEntityReferenceItem::getTargetTypes($this->getFieldSettings());
    $elements['view_mode'] = [];

    foreach ($entity_type_ids as $entity_type_id) {
      $elements[$entity_type_id] = [
        '#type' => 'container',
      ];
      $elements[$entity_type_id]['view_mode'] = [
        '#type' => 'select',
        '#options' => $this->entityDisplayRepository->getViewModeOptions($entity_type_id),
        '#title' => t('View mode for %entity', ['%entity' => $options[$entity_type_id]]),
        '#default_value' => $this->getSetting($entity_type_id)['view_mode'],
        '#required' => TRUE,
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      // Due to render caching and delayed calls, the viewElements() method
      // will be called later in the rendering process through a '#pre_render'
      // callback, so we need to generate a counter that takes into account
      // all the relevant information about this field and the referenced
      // entity that is being rendered.
      $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
        . $items->getFieldDefinition()->getTargetBundle()
        . $items->getName()
        // We include the referencing entity, so we can render default images
        // without hitting recursive protections.
        . $items->getEntity()->id()
        . $entity->getEntityTypeId()
        . $entity->id();

      if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
        static::$recursiveRenderDepth[$recursive_render_id]++;
      }
      else {
        static::$recursiveRenderDepth[$recursive_render_id] = 1;
      }

      // Protect ourselves from recursive rendering.
      if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %bundle_name bundle. Aborting rendering.', [
          '%entity_type' => $entity->getEntityTypeId(),
          '%entity_id' => $entity->id(),
          '%field_name' => $items->getName(),
          '%bundle_name' => $items->getFieldDefinition()->getTargetBundle(),
        ]);
        return $elements;
      }
      $entity_type_id = $entity->getEntityTypeId();
      $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);
      $elements[$delta] = $view_builder->view($entity, $this->getSetting($entity_type_id)['view_mode'], $entity->language()->getId());
      // Add a resource attribute to set the mapping property's value to the
      // entity's url. Since we don't know what the markup of the entity will
      // be, we shouldn't rely on it for structured data such as RDFa.
      if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
      }
    }

    return $elements;
  }

}
