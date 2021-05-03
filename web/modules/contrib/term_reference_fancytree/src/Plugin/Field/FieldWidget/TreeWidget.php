<?php

namespace Drupal\term_reference_fancytree\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A term reference tree widget.
 *
 * @FieldWidget(
 *   id = "term_reference_fancytree",
 *   label = @Translation("Term Reference Fancytree"),
 *   field_types = {"entity_reference"},
 *   multiple_values = TRUE
 * )
 */
class TreeWidget extends WidgetBase implements WidgetInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'select_all' => FALSE,
      'select_children' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['select_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select all'),
      '#description' => $this->t('Display "Select all" link. Note: Select all flag can affect performance since it will load all the children terms and also select them.'),
      '#default_value' => $this->getSetting('select_all'),
    ];

    $form['select_children'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select children'),
      '#description' => $this->t('Select children terms when parent is selected. Note: Select children flag can affect performance since it will load all the children terms and also select them.'),
      '#default_value' => $this->getSetting('select_children'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $select_all = $this->getSetting('select_all') ? 'Yes' : 'No';
    $summary[] = $this->t('Select all: @select_all', ['@select_all' => $select_all]);

    $select_children = $this->getSetting('select_children') ? 'Yes' : 'No';
    $summary[] = $this->t('Select children: @select_children', ['@select_children' => $select_children]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Obtain the target vocabularies from the field settings.
    $handler_settings = $this->getFieldSetting('handler_settings');
    if (isset($handler_settings['target_bundles'])) {
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple($handler_settings['target_bundles']);
    }
    else {
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    }
    // Define element settings.
    $element['#type'] = 'term_reference_fancytree';
    $element['#default_value'] = $items->getValue();
    $element['#vocabulary'] = $vocabularies;
    $element['#select_all'] = $this->getSetting('select_all');
    $element['#select_children'] = $this->getSetting('select_children');

    return $element;
  }

}
